<?php

use App\Enums\PaymentMethod;
use App\Filament\Business\Pages\Reports;
use App\Filament\Business\Widgets\Reports\ReportStats;
use App\Filament\Business\Widgets\SalesChart;
use App\Filament\Business\Widgets\StoreStats;
use App\Models\BusinessType;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BusinessProvisioner;
use App\Services\SaleService;
use Database\Seeders\BusinessTypeSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('renders every report and dashboard widget with real numbers', function (string $type): void {
    PlatformSetting::set('default_tax_rate', 10);
    $this->seed(BusinessTypeSeeder::class);
    $business = app(BusinessProvisioner::class)->provision(BusinessType::query()->where('key', $type)->firstOrFail(), ['name' => ['en' => 'S', 'ar' => 'س']], 'O', "{$type}@r.test", 'password');
    $owner = User::query()->where('email', "{$type}@r.test")->firstOrFail();
    $owner->update(['must_change_password' => false]);
    $this->actingAs($owner);
    Filament::setCurrentPanel('business');
    Filament::setTenant($business);
    setPermissionsTeamId($business->id);

    $product = Product::factory()->forBusiness($business)->create(['track_stock' => true]);
    $variant = ProductVariant::factory()->forProduct($product)->create(['stock_qty' => 5, 'retail_price_cents' => 2000, 'cost_price_cents' => 1000, 'low_stock_threshold' => 10]);
    app(SaleService::class)->createPosSale($business, [['variant_id' => $variant->id, 'qty' => 1]], [['method' => PaymentMethod::Cash->value, 'amount_cents' => 2200]]);

    Livewire::test(ReportStats::class, ['pageFilters' => ['preset' => 'today']])->assertSee('$20.00');

    foreach ((new Reports)->getWidgets() as $widget) {
        if ($widget::canView()) {
            Livewire::test($widget, ['pageFilters' => ['preset' => 'month']])->assertOk();
        }
    }

    Livewire::test(StoreStats::class)->assertSee('$20.00');
    Livewire::test(SalesChart::class)->assertOk();
    Livewire::test(Reports::class)->assertOk();
})->with(['grocery', 'car_showroom']);
