<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Business $business, Carbon $start, Carbon $end): array
    {
        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartiallyRefunded])
            ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get();

        $salesTotal = (int) $sales->sum('total_cents');
        $cogs = (int) $sales->sum('cogs_cents');
        $tax = (int) $sales->sum('tax_cents');

        $refunds = (int) \App\Models\Refund::query()
            ->where('business_id', $business->id)
            ->whereBetween('refunded_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->sum('amount_cents');

        $expenses = (int) Expense::query()
            ->where('business_id', $business->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount_cents');

        $orders = Order::query()
            ->where('business_id', $business->id)
            ->whereBetween('placed_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where('status', '!=', OrderStatus::Expired)
            ->count();

        $grossProfit = $salesTotal - $cogs - $refunds;

        return [
            'sales_total_cents' => $salesTotal,
            'sales_count' => $sales->count(),
            'average_sale_cents' => $sales->count() > 0 ? (int) round($salesTotal / $sales->count()) : 0,
            'cogs_cents' => $cogs,
            'tax_cents' => $tax,
            'refunds_cents' => $refunds,
            'expenses_cents' => $expenses,
            'gross_profit_cents' => $grossProfit,
            'net_profit_cents' => $grossProfit - $expenses,
            'orders_count' => $orders,
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
        return Sale::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartiallyRefunded])
            ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('date(sold_at) as date, sum(total_cents) as total_cents')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'total_cents' => (int) $row->total_cents,
            ]);
    }

    /**
     * @return Collection<int, array{name: string, qty: float, total_cents: int}>
     */
    public function topProducts(Business $business, Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return SaleItem::query()
            ->where('business_id', $business->id)
            ->whereHas('sale', function ($query) use ($start, $end): void {
                $query->whereIn('status', [SaleStatus::Completed, SaleStatus::PartiallyRefunded])
                    ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
            })
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
     * @return Collection<int, array{method: string, total_cents: int}>
     */
    public function paymentBreakdown(Business $business, Carbon $start, Carbon $end): Collection
    {
        return \App\Models\Payment::query()
            ->where('business_id', $business->id)
            ->where('status', PaymentStatus::Captured)
            ->whereBetween('paid_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('method, sum(amount_cents) as total_cents')
            ->groupBy('method')
            ->get()
            ->map(fn ($row): array => [
                'method' => (string) $row->method,
                'total_cents' => (int) $row->total_cents,
            ]);
    }

    /**
     * @return Collection<int, array{employee: string, sales_cents: int, commission_cents: int}>
     */
    public function commissions(Business $business, Carbon $start, Carbon $end): Collection
    {
        $employees = Employee::query()
            ->where('business_id', $business->id)
            ->where('commission_rate_bps', '>', 0)
            ->get();

        return $employees->map(function (Employee $employee) use ($start, $end): array {
            $sales = Sale::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [SaleStatus::Completed, SaleStatus::PartiallyRefunded])
                ->whereBetween('sold_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->get();

            $net = (int) $sales->sum(fn (Sale $sale): int => max(0, (int) $sale->subtotal_cents - (int) $sale->discount_cents));

            return [
                'employee' => $employee->name,
                'sales_cents' => $net,
                'commission_cents' => (int) round($net * $employee->commission_rate_bps / 10000),
            ];
        })->filter(fn (array $row): bool => $row['sales_cents'] > 0)->values();
    }

    public function inventoryValue(Business $business): int
    {
        return (int) ProductVariant::query()
            ->where('business_id', $business->id)
            ->selectRaw('sum(stock_qty * cost_price_cents) as value')
            ->value('value');
    }
}
