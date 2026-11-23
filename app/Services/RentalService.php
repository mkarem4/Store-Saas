<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductUnit;
use App\Enums\RentalRateType;
use App\Enums\RentalStatus;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Enums\VehicleStatus;
use App\Models\Payment;
use App\Models\RentalContract;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Car rental (S11, M2.8, D20): book → pick up (paid in full, revenue now) →
 * return (odometer, extra charges as revenue, deposit returned minus any
 * deductions). The deposit itself is never revenue. Contracts for the same
 * vehicle never overlap (rule 4).
 */
class RentalService
{
    public const PERIOD_DAYS = [RentalRateType::Daily->value => 1, RentalRateType::Weekly->value => 7, RentalRateType::Monthly->value => 30];

    public function totalFor(string $rateType, int $rateCents, Carbon $startsAt, Carbon $endsAt): int
    {
        $periodDays = self::PERIOD_DAYS[$rateType] ?? throw new InvalidArgumentException(__('Invalid rate type.'));
        $minutes = (int) round($startsAt->diffInMinutes($endsAt, absolute: true));
        $days = max(1, (int) ceil($minutes / 1440));

        return (int) ceil($days / $periodDays) * $rateCents;
    }

    public function book(
        Vehicle $vehicle,
        int $clientId,
        Carbon $startsAt,
        Carbon $endsAt,
        string $rateType,
        int $rateCents,
        int $depositCents = 0,
        ?string $notes = null,
        ?int $userId = null,
    ): RentalContract {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException(__('The return date must be after the pickup date.'));
        }

        if ($rateCents <= 0) {
            throw new InvalidArgumentException(__('Enter the rental rate.'));
        }

        return DB::transaction(function () use ($vehicle, $clientId, $startsAt, $endsAt, $rateType, $rateCents, $depositCents, $notes, $userId): RentalContract {
            /** @var Vehicle $locked */
            $locked = Vehicle::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($vehicle->id);

            if (in_array($locked->status, [VehicleStatus::Sold, VehicleStatus::Maintenance], true)) {
                throw new InvalidArgumentException(__('This vehicle cannot be rented (:status).', ['status' => $locked->status->label()]));
            }

            $this->assertNoOverlap($locked, $startsAt, $endsAt);

            return RentalContract::query()->create([
                'business_id' => $locked->business_id,
                'vehicle_id' => $locked->id,
                'client_id' => $clientId,
                'contract_number' => $this->nextNumber($locked->business_id),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'rate_type' => $rateType,
                'rate_cents' => $rateCents,
                'deposit_cents' => $depositCents,
                'total_cents' => $this->totalFor($rateType, $rateCents, $startsAt, $endsAt),
                'status' => RentalStatus::Reserved,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Hand the car over: the full rental is paid now and counted as revenue.
     */
    public function pickUp(RentalContract $contract, ?int $odometerOut, PaymentMethod $method, ?int $userId = null): RentalContract
    {
        return DB::transaction(function () use ($contract, $odometerOut, $method): RentalContract {
            /** @var RentalContract $locked */
            $locked = RentalContract::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($contract->id);

            if ($locked->status !== RentalStatus::Reserved) {
                throw new InvalidArgumentException(__('Only reserved contracts can be picked up.'));
            }

            $vehicle = Vehicle::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($locked->vehicle_id);

            if ($vehicle->status === VehicleStatus::Rented) {
                throw new InvalidArgumentException(__('This vehicle is still out on another rental.'));
            }

            $this->recordSale($locked, (int) $locked->total_cents, __('Rental :number', ['number' => $locked->contract_number]), $method, ['kind' => 'rental']);

            $locked->update([
                'status' => RentalStatus::Active,
                'odometer_out' => $odometerOut,
                'paid_at' => now(),
                'payment_method' => $method,
                'picked_up_at' => now(),
            ]);

            $vehicle->update(['status' => VehicleStatus::Rented, 'mileage' => $odometerOut ?? $vehicle->mileage]);

            return $locked->refresh();
        });
    }

    public function returnVehicle(
        RentalContract $contract,
        ?int $odometerIn,
        int $extraChargesCents = 0,
        ?string $extraChargesNote = null,
        bool $deductFromDeposit = true,
        PaymentMethod $method = PaymentMethod::Cash,
        ?int $userId = null,
    ): RentalContract {
        return DB::transaction(function () use ($contract, $odometerIn, $extraChargesCents, $extraChargesNote, $deductFromDeposit, $method): RentalContract {
            /** @var RentalContract $locked */
            $locked = RentalContract::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($contract->id);

            if ($locked->status !== RentalStatus::Active) {
                throw new InvalidArgumentException(__('Only active rentals can be returned.'));
            }

            if ($odometerIn !== null && $locked->odometer_out !== null && $odometerIn < $locked->odometer_out) {
                throw new InvalidArgumentException(__('The return odometer cannot be lower than at pickup.'));
            }

            $deducted = $deductFromDeposit ? min((int) $locked->deposit_cents, $extraChargesCents) : 0;

            if ($extraChargesCents > 0) {
                // Extra charges are revenue (late/damage); the part covered by
                // the deposit is paid out of it, the rest by the client now.
                $payments = array_values(array_filter([
                    $deducted > 0 ? ['method' => PaymentMethod::Cash, 'amount_cents' => $deducted, 'meta' => ['from_deposit' => true]] : null,
                    $extraChargesCents - $deducted > 0 ? ['method' => $method, 'amount_cents' => $extraChargesCents - $deducted, 'meta' => null] : null,
                ]));

                $this->recordSale($locked, $extraChargesCents, __('Extra charges · rental :number', ['number' => $locked->contract_number]), $payments, ['kind' => 'rental_extra', 'note' => $extraChargesNote]);
            }

            $locked->update([
                'status' => RentalStatus::Completed,
                'odometer_in' => $odometerIn,
                'extra_charges_cents' => $extraChargesCents,
                'extra_charges_note' => $extraChargesNote,
                'deposit_returned_cents' => (int) $locked->deposit_cents - $deducted,
                'returned_at' => now(),
            ]);

            Vehicle::query()->withoutGlobalScope('business')->whereKey($locked->vehicle_id)->update([
                'status' => VehicleStatus::Available,
                'mileage' => $odometerIn ?? DB::raw('mileage'),
            ]);

            return $locked->refresh();
        });
    }

    public function cancel(RentalContract $contract): RentalContract
    {
        if ($contract->status !== RentalStatus::Reserved) {
            throw new InvalidArgumentException(__('Only reserved contracts can be cancelled.'));
        }

        $contract->update(['status' => RentalStatus::Cancelled]);

        return $contract->refresh();
    }

    public function assertNoOverlap(Vehicle $vehicle, Carbon $startsAt, Carbon $endsAt, ?int $ignoreContractId = null): void
    {
        $overlap = RentalContract::query()
            ->withoutGlobalScope('business')
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', RentalStatus::blocking())
            ->when($ignoreContractId, fn ($query) => $query->whereKeyNot($ignoreContractId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException(__('This vehicle already has a contract in that period.'));
        }
    }

    /**
     * @param  PaymentMethod|array<int, array{method: PaymentMethod, amount_cents: int, meta: array<string, mixed>|null}>  $payments
     * @param  array<string, mixed>  $meta
     */
    private function recordSale(RentalContract $contract, int $amountCents, string $label, PaymentMethod|array $payments, array $meta): Sale
    {
        $payments = $payments instanceof PaymentMethod
            ? [['method' => $payments, 'amount_cents' => $amountCents, 'meta' => null]]
            : $payments;

        $sale = Sale::query()->create([
            'business_id' => $contract->business_id,
            'number' => app(SaleNumber::class)->next($contract->business_id),
            'channel' => SaleChannel::Rental,
            'client_id' => $contract->client_id,
            'subtotal_cents' => $amountCents,
            'discount_cents' => 0,
            'tax_rate' => 0,
            'tax_cents' => 0,
            'total_cents' => $amountCents,
            'cogs_cents' => 0,
            'status' => SaleStatus::Completed,
            'sold_at' => now(),
        ]);

        SaleItem::query()->create([
            'business_id' => $contract->business_id,
            'sale_id' => $sale->id,
            'name' => $label,
            'qty' => 1,
            'unit' => ProductUnit::Piece->value,
            'unit_price_cents' => $amountCents,
            'unit_cost_cents' => 0,
            'total_cents' => $amountCents,
            'meta' => $meta + ['rental_contract_id' => $contract->id, 'vehicle_id' => $contract->vehicle_id],
        ]);

        foreach ($payments as $payment) {
            Payment::query()->create([
                'business_id' => $contract->business_id,
                'payable_type' => $sale->getMorphClass(),
                'payable_id' => $sale->id,
                'method' => $payment['method'],
                'gateway' => $payment['method'] === PaymentMethod::Card ? 'manual' : null,
                'status' => PaymentStatus::Captured,
                'amount_cents' => $payment['amount_cents'],
                'paid_at' => now(),
                'meta' => $payment['meta'],
            ]);
        }

        return $sale;
    }

    private function nextNumber(int $businessId): string
    {
        $prefix = 'RC-'.now()->format('Ym').'-';

        $last = RentalContract::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->where('contract_number', 'like', $prefix.'%')
            ->orderByDesc('contract_number')
            ->value('contract_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
