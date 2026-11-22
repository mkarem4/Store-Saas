<?php

use App\Enums\ExpenseSource;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PurchaseService;
use App\Services\RecurringExpenseService;
use App\Services\ReportService;
use App\Services\SaleService;

beforeEach(function (): void {
    PlatformSetting::set('default_tax_rate', 10);
    PlatformSetting::set('tax_scope', 'all');
});

it('receives stock at a weighted average cost and requires a cost', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 10, 'cost_price_cents' => 400]);

    $receipt = app(PurchaseService::class)->receive($business, [
        ['variant_id' => $variant->id, 'qty' => 10, 'unit_cost_cents' => 600],
    ], reference: 'INV-1');

    expect($receipt->total_cost_cents)->toBe(6000)
        ->and((float) $variant->fresh()->stock_qty)->toBe(20.0)
        ->and($variant->fresh()->cost_price_cents)->toBe(500);

    expect(fn () => app(PurchaseService::class)->receive($business, [
        ['variant_id' => $variant->id, 'qty' => 1, 'unit_cost_cents' => 0],
    ]))->toThrow(InvalidArgumentException::class, 'purchase cost');
});

it('matches a hand-computed net profit (scenario 14)', function (): void {
    $business = Business::factory()->create();
    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 50, 'retail_price_cents' => 2000, 'cost_price_cents' => 1200]);

    // 5 × 20.00 = 100.00 + 10% tax.
    $sale = app(SaleService::class)->createPosSale($business, [['variant_id' => $variant->id, 'qty' => 5]], [['method' => PaymentMethod::Cash->value, 'amount_cents' => 11000]]);
    // Return one unit: −20.00 revenue, −12.00 COGS.
    app(SaleService::class)->refund($sale, [['sale_item_id' => $sale->items()->first()->id, 'qty' => 1]]);

    $category = ExpenseCategory::query()->firstOrCreate(['business_id' => $business->id, 'key' => 'rent'], ['name' => ['en' => 'Rent', 'ar' => 'إيجار']]);
    Expense::query()->create(['business_id' => $business->id, 'expense_category_id' => $category->id, 'amount_cents' => 3000, 'description' => 'Rent', 'expense_date' => now()->toDateString(), 'source' => ExpenseSource::Manual]);

    $report = app(ReportService::class)->summary($business, now(), now());

    // revenue 80.00, COGS 48.00, gross 32.00, expenses 30.00 → net 2.00; tax 8.00 kept apart.
    expect($report['revenue_cents'])->toBe(8000)
        ->and($report['cogs_cents'])->toBe(4800)
        ->and($report['gross_profit_cents'])->toBe(3200)
        ->and($report['expenses_cents'])->toBe(3000)
        ->and($report['net_profit_cents'])->toBe(200)
        ->and($report['tax_cents'])->toBe(800);

    $tax = app(ReportService::class)->taxReport($business, now(), now());
    expect($tax->first()['tax_cents'])->toBe(1000)
        ->and($tax->first()['rate'])->toBe(10.0);
});

it('creates recurring rent once per period', function (): void {
    $business = Business::factory()->create();
    Expense::query()->create([
        'business_id' => $business->id,
        'amount_cents' => 250000,
        'description' => 'Shop rent',
        'expense_date' => now()->subMonths(2)->toDateString(),
        'source' => ExpenseSource::Manual,
        'is_recurring' => true,
        'recurrence' => 'monthly',
        'next_due_date' => now()->subMonth()->toDateString(),
    ]);

    expect(app(RecurringExpenseService::class)->generateDue())->toBe(2)
        ->and(app(RecurringExpenseService::class)->generateDue())->toBe(0)
        ->and(Expense::query()->where('source', ExpenseSource::Recurring)->sum('amount_cents'))->toBe(500000);

    // The template itself never counts as an expense.
    expect(app(ReportService::class)->summary($business, now()->subMonths(3), now())['expenses_cents'])->toBe(500000);
});
