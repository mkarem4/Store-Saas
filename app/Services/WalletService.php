<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Business;
use App\Models\BusinessWallet;
use App\Models\Order;
use App\Models\PayoutMethod;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\PayoutStatusChanged;
use App\Support\Money;
use App\Support\PlatformConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Store wallet ledger (D26). App payments settle into the platform's Stripe
 * account; each store's share is tracked here and paid out manually.
 *
 * wallet_transactions is the append-only source of truth; business_wallets
 * caches the pending/available sums and is only changed under a row lock.
 */
class WalletService
{
    public function walletFor(Business|int $business): BusinessWallet
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        return BusinessWallet::query()
            ->withoutGlobalScope('business')
            ->firstOrCreate(['business_id' => $businessId]);
    }

    /**
     * Credit a completed order: gross paid amount minus the platform commission
     * and the actual gateway fee, held as pending for the hold period.
     */
    public function creditCompletedOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $wallet = $this->lock($order->business_id);

            $alreadyCredited = WalletTransaction::query()
                ->withoutGlobalScope('business')
                ->where('order_id', $order->id)
                ->where('type', WalletTransactionType::Sale)
                ->exists();

            if ($alreadyCredited) {
                return;
            }

            $holdDays = PlatformConfig::walletHoldDays();
            $status = $holdDays > 0 ? WalletTransactionStatus::Pending : WalletTransactionStatus::Available;
            $availableAt = now()->addDays($holdDays);

            $entries = [
                [WalletTransactionType::Sale, (int) $order->total_cents, __('Order :number', ['number' => $order->number])],
                [WalletTransactionType::PlatformFee, -(int) $order->platform_fee_cents, __('Platform commission (:rate%)', ['rate' => PlatformConfig::commissionRate()])],
                [WalletTransactionType::GatewayFee, -(int) $order->gateway_fee_cents, __('Payment processing fee')],
            ];

            $net = 0;

            foreach ($entries as [$type, $amount, $description]) {
                if ($amount === 0 && $type !== WalletTransactionType::Sale) {
                    continue;
                }

                $this->record($wallet, $type, $amount, $status, $description, $availableAt, orderId: $order->id);
                $net += $amount;
            }

            $wallet->lifetime_earnings_cents += $net;
            $wallet->save();
        });
    }

    /**
     * Debit a refund on a completed order and give back the matching share of
     * the platform commission (rule 20). Stripe keeps its fee on refunds.
     * The wallet may go negative if the store was already paid out (rule 23).
     */
    public function debitRefund(Order $order, int $refundCents, int $commissionReversalCents): void
    {
        if ($refundCents <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $refundCents, $commissionReversalCents): void {
            $wallet = $this->lock($order->business_id);

            /** @var WalletTransaction|null $credit */
            $credit = WalletTransaction::query()
                ->withoutGlobalScope('business')
                ->where('order_id', $order->id)
                ->where('type', WalletTransactionType::Sale)
                ->first();

            if ($credit === null) {
                return;
            }

            // Follow the original credit: while it is still on hold the refund
            // reduces the pending balance, otherwise the available one.
            $status = $credit->status;
            $availableAt = $credit->available_at;

            $this->record($wallet, WalletTransactionType::Refund, -$refundCents, $status, __('Refund on order :number', ['number' => $order->number]), $availableAt, orderId: $order->id);

            if ($commissionReversalCents > 0) {
                $this->record($wallet, WalletTransactionType::PlatformFeeReversal, $commissionReversalCents, $status, __('Commission reversal on order :number', ['number' => $order->number]), $availableAt, orderId: $order->id);
            }

            $wallet->lifetime_earnings_cents += $commissionReversalCents - $refundCents;
            $wallet->save();
        });
    }

    /**
     * Move matured pending entries to available. Returns how many moved.
     */
    public function releaseMatured(?Carbon $now = null): int
    {
        $now ??= now();
        $moved = 0;

        $businessIds = WalletTransaction::query()
            ->withoutGlobalScope('business')
            ->where('status', WalletTransactionStatus::Pending)
            ->where('available_at', '<=', $now)
            ->distinct()
            ->pluck('business_id');

        foreach ($businessIds as $businessId) {
            $moved += DB::transaction(function () use ($businessId, $now): int {
                $wallet = $this->lock((int) $businessId);

                $due = WalletTransaction::query()
                    ->withoutGlobalScope('business')
                    ->where('business_id', $businessId)
                    ->where('status', WalletTransactionStatus::Pending)
                    ->where('available_at', '<=', $now)
                    ->lockForUpdate()
                    ->get();

                $sum = (int) $due->sum('amount_cents');

                WalletTransaction::query()
                    ->withoutGlobalScope('business')
                    ->whereIn('id', $due->modelKeys())
                    ->update(['status' => WalletTransactionStatus::Available]);

                $wallet->pending_cents -= $sum;
                $wallet->available_cents += $sum;
                $wallet->save();

                return $due->count();
            });
        }

        return $moved;
    }

    public function requestPayout(
        Business $business,
        int $amountCents,
        ?PayoutMethod $method = null,
        ?string $note = null,
        ?int $userId = null,
    ): PayoutRequest {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException(__('Enter an amount greater than zero.'));
        }

        $minimum = PlatformConfig::minimumPayoutCents();

        if ($minimum > 0 && $amountCents < $minimum) {
            throw new InvalidArgumentException(__('The minimum payout is :amount.', ['amount' => Money::format($minimum)]));
        }

        if ($method !== null && (int) $method->business_id !== (int) $business->id) {
            throw new InvalidArgumentException(__('Invalid payout method.'));
        }

        return DB::transaction(function () use ($business, $amountCents, $method, $note, $userId): PayoutRequest {
            $wallet = $this->lock($business->id);

            if ($amountCents > $wallet->available_cents) {
                throw new InvalidArgumentException(__('You can withdraw up to :amount.', [
                    'amount' => Money::format(max(0, $wallet->available_cents)),
                ]));
            }

            $payout = PayoutRequest::query()->create([
                'business_id' => $business->id,
                'payout_method_id' => $method?->id,
                'amount_cents' => $amountCents,
                'status' => PayoutStatus::Requested,
                'method_snapshot' => $method === null ? null : [
                    'type' => $method->type->value,
                    'summary' => $method->summary(),
                ],
                'note' => $note,
                'requested_by' => $userId,
            ]);

            // Hold the funds immediately so they cannot be requested twice.
            $this->record($wallet, WalletTransactionType::Payout, -$amountCents, WalletTransactionStatus::Available, __('Payout request #:id', ['id' => $payout->id]), now(), payoutId: $payout->id);
            $wallet->save();

            return $payout;
        });
    }

    public function approvePayout(PayoutRequest $payout, User $admin, ?string $note = null): PayoutRequest
    {
        return $this->transition($payout, [PayoutStatus::Requested], function (PayoutRequest $locked) use ($admin, $note): void {
            $locked->update([
                'status' => PayoutStatus::Approved,
                'processed_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $note ?? $locked->admin_note,
            ]);
        });
    }

    public function markPayoutPaid(PayoutRequest $payout, User $admin, string $reference, ?string $note = null): PayoutRequest
    {
        if (blank($reference)) {
            throw new InvalidArgumentException(__('Enter the transfer reference.'));
        }

        return $this->transition($payout, [PayoutStatus::Requested, PayoutStatus::Approved], function (PayoutRequest $locked) use ($admin, $reference, $note): void {
            $locked->update([
                'status' => PayoutStatus::Paid,
                'processed_by' => $admin->id,
                'approved_at' => $locked->approved_at ?? now(),
                'paid_at' => now(),
                'reference' => $reference,
                'admin_note' => $note ?? $locked->admin_note,
            ]);

            $wallet = $this->lock($locked->business_id);
            $wallet->lifetime_payouts_cents += $locked->amount_cents;
            $wallet->save();
        });
    }

    public function rejectPayout(PayoutRequest $payout, User $admin, string $reason): PayoutRequest
    {
        if (blank($reason)) {
            throw new InvalidArgumentException(__('Enter a reason for rejecting.'));
        }

        return $this->transition($payout, [PayoutStatus::Requested, PayoutStatus::Approved], function (PayoutRequest $locked) use ($admin, $reason): void {
            $locked->update([
                'status' => PayoutStatus::Rejected,
                'processed_by' => $admin->id,
                'rejected_at' => now(),
                'admin_note' => $reason,
            ]);

            $wallet = $this->lock($locked->business_id);
            $this->record($wallet, WalletTransactionType::PayoutReversal, $locked->amount_cents, WalletTransactionStatus::Available, __('Payout request #:id rejected', ['id' => $locked->id]), now(), payoutId: $locked->id);
            $wallet->save();
        });
    }

    /**
     * Manual correction by the platform owner (positive or negative).
     */
    public function adjust(Business $business, int $amountCents, string $description, ?int $userId = null): WalletTransaction
    {
        if ($amountCents === 0) {
            throw new InvalidArgumentException(__('Enter a non-zero amount.'));
        }

        return DB::transaction(function () use ($business, $amountCents, $description, $userId): WalletTransaction {
            $wallet = $this->lock($business->id);
            $transaction = $this->record($wallet, WalletTransactionType::Adjustment, $amountCents, WalletTransactionStatus::Available, $description, now(), meta: ['user_id' => $userId]);
            $wallet->lifetime_earnings_cents += $amountCents;
            $wallet->save();

            return $transaction;
        });
    }

    /**
     * @param  array<int, PayoutStatus>  $from
     * @param  callable(PayoutRequest): void  $apply
     */
    private function transition(PayoutRequest $payout, array $from, callable $apply): PayoutRequest
    {
        $updated = DB::transaction(function () use ($payout, $from, $apply): PayoutRequest {
            /** @var PayoutRequest $locked */
            $locked = PayoutRequest::query()
                ->withoutGlobalScope('business')
                ->lockForUpdate()
                ->findOrFail($payout->id);

            if (! in_array($locked->status, $from, true)) {
                throw new InvalidArgumentException(__('This payout request is already :status.', ['status' => $locked->status->label()]));
            }

            $apply($locked);

            return $locked->refresh();
        });

        $this->notifyBusiness($updated);

        return $updated;
    }

    private function notifyBusiness(PayoutRequest $payout): void
    {
        $recipients = User::query()
            ->where('business_id', $payout->business_id)
            ->where('role', 'business_owner')
            ->get();

        foreach ($recipients as $user) {
            $user->notify(new PayoutStatusChanged($payout));
        }
    }

    private function lock(int $businessId): BusinessWallet
    {
        $this->walletFor($businessId);

        return BusinessWallet::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(
        BusinessWallet $wallet,
        WalletTransactionType $type,
        int $amountCents,
        WalletTransactionStatus $status,
        string $description,
        ?Carbon $availableAt,
        ?int $orderId = null,
        ?int $payoutId = null,
        ?array $meta = null,
    ): WalletTransaction {
        if ($status === WalletTransactionStatus::Pending) {
            $wallet->pending_cents += $amountCents;
        } else {
            $wallet->available_cents += $amountCents;
        }

        return WalletTransaction::query()->create([
            'business_id' => $wallet->business_id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'status' => $status,
            'order_id' => $orderId,
            'payout_request_id' => $payoutId,
            'description' => $description,
            'available_at' => $availableAt,
            'meta' => $meta,
        ]);
    }
}
