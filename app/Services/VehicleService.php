<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SaleChannel;
use App\Enums\SaleStatus;
use App\Enums\VehicleStatus;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Vehicle;
use App\Models\VehicleCost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Car showroom (S10, M2.7, M2.9): maintenance costs roll into the car's total
 * cost; selling records a sale whose COGS is that total cost, attributed to
 * the selling employee for commission.
 */
class VehicleService
{
    public function addCost(Vehicle $vehicle, int $amountCents, string $description, ?Carbon $date = null, ?int $userId = null): VehicleCost
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException(__('Enter an amount greater than zero.'));
        }

        if ($vehicle->status === VehicleStatus::Sold) {
            throw new InvalidArgumentException(__('This vehicle is already sold.'));
        }

        return VehicleCost::query()->create([
            'business_id' => $vehicle->business_id,
            'vehicle_id' => $vehicle->id,
            'description' => $description,
            'amount_cents' => $amountCents,
            'incurred_at' => ($date ?? now())->toDateString(),
            'user_id' => $userId,
        ]);
    }

    public function reserve(Vehicle $vehicle): Vehicle
    {
        if ($vehicle->status !== VehicleStatus::Available) {
            throw new InvalidArgumentException(__('Only available vehicles can be reserved.'));
        }

        $vehicle->update(['status' => VehicleStatus::Reserved]);

        return $vehicle->refresh();
    }

    public function release(Vehicle $vehicle): Vehicle
    {
        if ($vehicle->status === VehicleStatus::Reserved) {
            $vehicle->update(['status' => VehicleStatus::Available]);
        }

        return $vehicle->refresh();
    }

    /**
     * @param  array<int, array{method: string, amount_cents: int, gateway_ref?: string|null}>  $payments
     */
    public function sell(
        Vehicle $vehicle,
        int $soldPriceCents,
        int $clientId,
        array $payments,
        ?int $employeeId = null,
        ?int $userId = null,
        ?string $notes = null,
    ): Sale {
        if ($soldPriceCents <= 0) {
            throw new InvalidArgumentException(__('Enter the selling price.'));
        }

        $paid = (int) array_sum(array_column($payments, 'amount_cents'));

        if ($paid !== $soldPriceCents) {
            throw new InvalidArgumentException(__('Payments must equal the sale total.'));
        }

        if ($employeeId !== null && ! Employee::query()->whereKey($employeeId)->where('business_id', $vehicle->business_id)->exists()) {
            throw new InvalidArgumentException(__('Invalid employee.'));
        }

        return DB::transaction(function () use ($vehicle, $soldPriceCents, $clientId, $payments, $employeeId, $userId, $notes): Sale {
            /** @var Vehicle $locked */
            $locked = Vehicle::query()->withoutGlobalScope('business')->lockForUpdate()->findOrFail($vehicle->id);

            // Rule 3: sold once; a reservation blocks other channels but the
            // owner can still complete the reserved sale here.
            if (! in_array($locked->status, [VehicleStatus::Available, VehicleStatus::Reserved], true)) {
                throw new InvalidArgumentException(__('This vehicle cannot be sold (:status).', ['status' => $locked->status->label()]));
            }

            $totalCost = $locked->totalCostCents();

            $sale = Sale::query()->create([
                'business_id' => $locked->business_id,
                'number' => app(SaleNumber::class)->next($locked->business_id),
                'channel' => SaleChannel::Vehicle,
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'subtotal_cents' => $soldPriceCents,
                'discount_cents' => 0,
                'tax_rate' => 0,
                'tax_cents' => 0,
                'total_cents' => $soldPriceCents,
                'cogs_cents' => $totalCost,
                'status' => SaleStatus::Completed,
                'sold_at' => now(),
                'notes' => $notes,
            ]);

            SaleItem::query()->create([
                'business_id' => $locked->business_id,
                'sale_id' => $sale->id,
                'name' => $locked->displayName().' · '.$locked->vin,
                'qty' => 1,
                'unit' => 'piece',
                'unit_price_cents' => $soldPriceCents,
                'unit_cost_cents' => $totalCost,
                'total_cents' => $soldPriceCents,
                'meta' => ['vehicle_id' => $locked->id, 'vin' => $locked->vin],
            ]);

            foreach ($payments as $payment) {
                Payment::query()->create([
                    'business_id' => $locked->business_id,
                    'payable_type' => $sale->getMorphClass(),
                    'payable_id' => $sale->id,
                    'method' => PaymentMethod::from($payment['method']),
                    'gateway' => $payment['method'] === 'card' ? 'manual' : null,
                    'gateway_ref' => $payment['gateway_ref'] ?? null,
                    'status' => PaymentStatus::Captured,
                    'amount_cents' => $payment['amount_cents'],
                    'paid_at' => now(),
                ]);
            }

            $locked->update([
                'status' => VehicleStatus::Sold,
                'sold_price_cents' => $soldPriceCents,
                'buyer_client_id' => $clientId,
                'sold_by_employee_id' => $employeeId,
                'sold_at' => now(),
            ]);

            activity('vehicles')->performedOn($locked)->causedBy($userId)
                ->withProperties(['sold_price_cents' => $soldPriceCents, 'total_cost_cents' => $totalCost, 'sale_id' => $sale->id])
                ->log('vehicle.sold');

            return $sale;
        });
    }
}
