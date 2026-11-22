<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Support\Money;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public readonly PayoutRequest $payout) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Stored in the Filament database-notification format so it shows in the
     * business panel's notification drawer.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $status = $this->payout->status;

        return FilamentNotification::make()
            ->title(__('Payout :status', ['status' => $status->label()]))
            ->body(__('Payout request #:id for :amount is now :status.', [
                'id' => $this->payout->id,
                'amount' => Money::format($this->payout->amount_cents),
                'status' => $status->label(),
            ]).($this->payout->reference ? ' '.__('Reference: :ref', ['ref' => $this->payout->reference]) : ''))
            ->color($status->color())
            ->getDatabaseMessage();
    }
}
