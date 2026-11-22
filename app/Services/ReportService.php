<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\TimeEntry;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Management reporting (M6):
 *
 *   revenue      = Σ sales (total − tax) − Σ refunds (amount − tax)
 *   COGS         = Σ sale COGS − Σ refunded COGS
 *   gross profit = revenue − COGS
 *   expenses     = rent + payroll (incl. commissions) + payment fees +
 *                  platform commission + everything else
 *   net profit   = gross profit − expenses
 *
 * Period boundaries are the store's local days (M6.1).
 */
class ReportService
{
    /**
     * @return array{0: Carbon, 1: Carbon} UTC instants for the local period
     */
    public function bounds(Business $business, Carbon $start, Carbon $end): array
    {
        $timezone = $business->timezone ?: config('app.timezone');

        return [
            Carbon::parse($start->toDateString(), $timezone)->startOfDay()->utc(),
            Carbon::parse($end->toDateString(), $timezone)->endOfDay()->utc(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Business $business, Carbon $start, Carbon $end): array
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        $sales = $this->salesQuery($business, $from, $to)->get(['id', 'channel', 'total_cents', 'tax_cents', 'cogs_cents', 'discount_cents']);
        $refunds = Refund::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereBetween('refunded_at', [$from, $to])
            ->get(['amount_cents', 'tax_cents', 'cogs_cents']);

        $grossSales = (int) $sales->sum(fn (Sale $sale): int => (int) $sale->total_cents - (int) $sale->tax_cents);
        $refundsNet = (int) $refunds->sum(fn (Refund $refund): int => (int) $refund->amount_cents - (int) $refund->tax_cents);
        $revenue = $grossSales - $refundsNet;
        $cogs = (int) $sales->sum('cogs_cents') - (int) $refunds->sum('cogs_cents');
        $taxCollected = (int) $sales->sum('tax_cents') - (int) $refunds->sum('tax_cents');

        $expenses = Expense::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('is_recurring', false)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->whereDate('expense_date', '<=', $end->toDateString())
            ->with('category')
            ->get(['id', 'expense_category_id', 'amount_cents', 'source']);

        $expensesTotal = (int) $expenses->sum('amount_cents');
        $grossProfit = $revenue - $cogs;

        $orders = Order::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereBetween('placed_at', [$from, $to])
            ->whereNotIn('status', [OrderStatus::Expired, OrderStatus::Rejected])
            ->count();

        return [
            'revenue_cents' => $revenue,
            'sales_total_cents' => $grossSales,
            'sales_count' => $sales->count(),
            'average_sale_cents' => $sales->count() > 0 ? (int) round($grossSales / $sales->count()) : 0,
            'discounts_cents' => (int) $sales->sum('discount_cents'),
            'refunds_cents' => $refundsNet,
            'tax_cents' => $taxCollected,
            'cogs_cents' => $cogs,
            'gross_profit_cents' => $grossProfit,
            'expenses_cents' => $expensesTotal,
            'net_profit_cents' => $grossProfit - $expensesTotal,
            'orders_count' => $orders,
            'by_channel' => $sales->groupBy(fn (Sale $sale): string => $sale->channel->value)
                ->map(fn (Collection $group): int => (int) $group->sum(fn (Sale $sale): int => (int) $sale->total_cents - (int) $sale->tax_cents))
                ->all(),
            'expenses_by_category' => $expenses->groupBy(fn (Expense $expense): string => (string) ($expense->category?->translated('name') ?: __('Uncategorized')))
                ->map(fn (Collection $group): int => (int) $group->sum('amount_cents'))
                ->sortDesc()
                ->all(),
            'daily_sales' => $this->dailySales($business, $start, $end),
            'top_products' => $this->topProducts($business, $start, $end),
            'payment_breakdown' => $this->paymentBreakdown($business, $start, $end),
            'commissions' => $this->commissions($business, $start, $end),
            'inventory_value_cents' => $this->inventoryValue($business),
        ];
    }

    /**
     * @return Collection<int, array{date: string, total_cents: int}>
     */
    public function dailySales(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);
        $timezone = $business->timezone ?: config('app.timezone');

        return $this->salesQuery($business, $from, $to)
            ->get(['sold_at', 'total_cents', 'tax_cents'])
            ->groupBy(fn (Sale $sale): string => $sale->sold_at->copy()->setTimezone($timezone)->toDateString())
            ->map(fn (Collection $group, string $date): array => [
                'date' => $date,
                'total_cents' => (int) $group->sum(fn (Sale $sale): int => (int) $sale->total_cents - (int) $sale->tax_cents),
            ])
            ->sortKeys()
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, qty: float, total_cents: int}>
     */
    public function topProducts(Business $business, Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        return SaleItem::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereHas('sale', fn ($query) => $query->withoutGlobalScope('business')
                ->where('status', '!=', SaleStatus::Void)
                ->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('name, sum(qty) as qty, sum(total_cents) as total_cents')
            ->groupBy('name')
            ->orderByDesc('total_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'qty' => (float) $row->qty,
                'total_cents' => (int) $row->total_cents,
            ]);
    }

    /**
     * Cash vs card vs online (M6.2).
     *
     * @return Collection<int, array{method: string, total_cents: int}>
     */
    public function paymentBreakdown(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        return Payment::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('status', PaymentStatus::Captured)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, sum(amount_cents) as total_cents')
            ->groupBy('method')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) ($row->method instanceof \BackedEnum ? $row->method->value : $row->method),
                'total_cents' => (int) $row->total_cents,
            ]);
    }

    /**
     * Commission per employee: rate × sales net of discounts and returns (D16).
     *
     * @return Collection<int, array{employee_id: int, employee: string, sales_cents: int, commission_cents: int}>
     */
    public function commissions(Business $business, Carbon $start, Carbon $end): Collection
    {
        $payroll = app(PayrollService::class);

        return Employee::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('commission_rate_bps', '>', 0)
            ->get()
            ->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'employee' => $employee->name,
                'sales_cents' => $payroll->netSales($employee, $start, $end),
                'commission_cents' => $payroll->commission($employee, $start, $end),
            ])
            ->filter(fn (array $row): bool => $row['sales_cents'] > 0)
            ->values();
    }

    /**
     * Sales per employee (M6.3), net of tax, discounts and returns.
     *
     * @return Collection<int, array{employee: string, sales_count: int, sales_cents: int}>
     */
    public function salesByEmployee(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        return Employee::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->get()
            ->map(fn (Employee $employee): array => [
                'employee' => $employee->name,
                'sales_count' => Sale::query()->withoutGlobalScope('business')->where('employee_id', $employee->id)
                    ->where('status', '!=', SaleStatus::Void)->whereBetween('sold_at', [$from, $to])->count(),
                'sales_cents' => app(PayrollService::class)->netSales($employee, $start, $end),
            ])
            ->filter(fn (array $row): bool => $row['sales_count'] > 0)
            ->sortByDesc('sales_cents')
            ->values();
    }

    /**
     * @return Collection<int, array{employee: string, minutes: int, auto_closed: int}>
     */
    public function hoursByEmployee(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        return TimeEntry::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereBetween('clock_in_at', [$from, $to])
            ->whereNotNull('clock_out_at')
            ->with('employee')
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $entries): array => [
                'employee' => (string) $entries->first()->employee?->name,
                'minutes' => (int) $entries->sum(fn (TimeEntry $entry): int => $entry->minutesWorked()),
                'auto_closed' => $entries->where('is_auto_closed', true)->whereNull('reviewed_at')->count(),
            ])
            ->sortByDesc('minutes')
            ->values();
    }

    /**
     * Tax collected on POS and app sales for the period (M6.6), grouped by
     * the rate applied. Each store has one region, so rate = region here.
     *
     * @return Collection<int, array{region: string, rate: float, taxable_cents: int, tax_cents: int}>
     */
    public function taxReport(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);
        $region = trim($business->country.'-'.$business->state, '-');

        return $this->salesQuery($business, $from, $to)
            ->where('tax_cents', '>', 0)
            ->get(['tax_rate', 'total_cents', 'tax_cents', 'channel'])
            ->groupBy(fn (Sale $sale): string => number_format((float) $sale->tax_rate, 3).'|'.$sale->channel->value)
            ->map(fn (Collection $group): array => [
                'region' => $region,
                'channel' => $group->first()->channel->label(),
                'rate' => (float) $group->first()->tax_rate,
                'taxable_cents' => (int) $group->sum(fn (Sale $sale): int => (int) $sale->total_cents - (int) $sale->tax_cents),
                'tax_cents' => (int) $group->sum('tax_cents'),
            ])
            ->values();
    }

    /**
     * Profit per sold car (M6.4).
     *
     * @return Collection<int, array{vehicle: string, vin: string, sold_cents: int, cost_cents: int, profit_cents: int, sold_by: ?string}>
     */
    public function vehicleProfits(Business $business, Carbon $start, Carbon $end): Collection
    {
        [$from, $to] = $this->bounds($business, $start, $end);

        return Vehicle::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereNotNull('sold_at')
            ->whereBetween('sold_at', [$from, $to])
            ->with(['costs', 'soldBy'])
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'vehicle' => $vehicle->displayName(),
                'vin' => (string) $vehicle->vin,
                'sold_cents' => (int) $vehicle->sold_price_cents,
                'cost_cents' => $vehicle->totalCostCents(),
                'profit_cents' => $vehicle->profitCents(),
                'sold_by' => $vehicle->soldBy?->name,
            ]);
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function lowStock(Business $business): Collection
    {
        return ProductVariant::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->whereHas('product', fn ($query) => $query->where('track_stock', true))
            ->with('product')
            ->orderBy('stock_qty')
            ->get();
    }

    public function inventoryValue(Business $business): int
    {
        return (int) ProductVariant::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->whereHas('product', fn ($query) => $query->where('track_stock', true))
            ->where('stock_qty', '>', 0)
            ->selectRaw('sum(stock_qty * cost_price_cents) as value')
            ->value('value');
    }

    private function salesQuery(Business $business, Carbon $from, Carbon $to): Builder
    {
        return Sale::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('status', '!=', SaleStatus::Void)
            ->whereBetween('sold_at', [$from, $to]);
    }
}
