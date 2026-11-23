<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Models\ProductVariant;
use App\Services\ReportService;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class LowStockTable extends ReportTable
{
    protected static ?int $sort = 9;

    public static function canView(): bool
    {
        return ! BusinessPanel::isType([BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value, BusinessTypeKey::Restaurant->value]);
    }

    protected function heading(): string
    {
        return __('Stock worth :amount at cost', ['amount' => Money::format(app(ReportService::class)->inventoryValue(BusinessPanel::business()), BusinessPanel::currency())]);
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        return $service->lowStock(BusinessPanel::business())->take(20)->map(fn (ProductVariant $variant): array => [
            'name' => $variant->displayName(),
            'stock' => (float) $variant->stock_qty,
            'threshold' => (float) $variant->low_stock_threshold,
        ])->all();
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('name')->label(__('Low stock'))->wrap(),
            TextColumn::make('stock')->label(__('In stock'))->numeric(3)->alignEnd()->color('danger'),
            TextColumn::make('threshold')->label(__('Alert at'))->numeric(3)->alignEnd(),
        ];
    }
}
