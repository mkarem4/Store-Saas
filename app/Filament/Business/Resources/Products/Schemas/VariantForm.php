<?php

namespace App\Filament\Business\Resources\Products\Schemas;

use App\Enums\JewelryPricingMode;
use App\Enums\MakingType;
use App\Enums\ProductUnit;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Business;
use App\Services\Gold\GoldPriceService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Unique;

/**
 * Price/stock inputs of a variant (M2.1, D8, D11, D12, D5).
 */
final class VariantForm
{
    /**
     * @return array<int, Component>
     */
    public static function components(bool $creating, string $prefix = '', ?int $ignoreId = null): array
    {
        $type = BusinessPanel::typeKey();
        $businessId = BusinessPanel::business()?->id;
        $unique = fn (string $column) => fn (Unique $rule) => $rule->where('business_id', $businessId)->when($ignoreId, fn ($rule) => $rule->whereNot('id', $ignoreId));

        $components = [
            Grid::make(4)->schema([
                TextInput::make("{$prefix}sku")->label(__('SKU'))->maxLength(64)
                    ->unique('product_variants', 'sku', ignoreRecord: ! $creating, modifyRuleUsing: $unique('sku')),
                TextInput::make("{$prefix}barcode")->label(__('Barcode'))->maxLength(64)
                    ->unique('product_variants', 'barcode', ignoreRecord: ! $creating, modifyRuleUsing: $unique('barcode')),
                Select::make("{$prefix}unit")->label(__('Sold by'))->options(ProductUnit::class)->default(ProductUnit::Piece)->required()
                    ->visible($type === 'grocery')
                    ->helperText(__('Kilogram allows weights like 1.250.')),
                TextInput::make("{$prefix}low_stock_threshold")->label(__('Low stock alert at'))->numeric()->minValue(0)
                    ->visible($type !== 'restaurant'),
            ]),
            Grid::make(3)->schema([
                MoneyInput::make("{$prefix}cost_price_cents")->label(__('Cost'))
                    ->helperText($creating ? __('Purchase cost per unit.') : __('Weighted average; updated by stock receipts.'))
                    ->disabled(! $creating && $type !== 'restaurant')
                    ->dehydrated($creating || $type === 'restaurant'),
                MoneyInput::make("{$prefix}wholesale_price_cents")->label(__('Wholesale price'))->visible($type !== 'restaurant'),
                MoneyInput::make("{$prefix}retail_price_cents")->label(__('Retail price'))->required(),
            ]),
        ];

        if ($creating && $type !== 'restaurant') {
            $components[] = Grid::make(3)->schema([
                TextInput::make("{$prefix}opening_qty")->label(__('Opening stock'))->numeric()->minValue(0)->default(0)
                    ->helperText(__('Received at the cost above.')),
                DatePicker::make("{$prefix}expiry_date")->label(__('Expiry date'))->visible($type === 'grocery'),
            ]);
        } elseif ($type === 'grocery') {
            $components[] = DatePicker::make("{$prefix}expiry_date")->label(__('Expiry date'));
        }

        if ($type === 'jewelry') {
            $components[] = self::jewelry("{$prefix}jewelry.");
        }

        return $components;
    }

    public static function jewelry(string $prefix): Fieldset
    {
        $karats = app(GoldPriceService::class)->karats(BusinessPanel::business() ?? new Business);

        return Fieldset::make(__('Jewelry pricing'))->columns(3)->schema([
            Select::make("{$prefix}pricing_mode")->label(__('Pricing'))->options(JewelryPricingMode::class)
                ->default(JewelryPricingMode::Fixed)->required()->live(),
            TextInput::make("{$prefix}weight_g")->label(__('Weight (g)'))->numeric()->minValue(0)->step('0.001')
                ->required(fn (Get $get): bool => self::byWeight($get("{$prefix}pricing_mode"))),
            Select::make("{$prefix}karat")->label(__('Karat'))->options(array_combine($karats, array_map(fn ($k) => $k.'K', $karats)))
                ->required(fn (Get $get): bool => self::byWeight($get("{$prefix}pricing_mode"))),
            TextInput::make("{$prefix}metal")->label(__('Metal'))->default('gold'),
            Select::make("{$prefix}making_type")->label(__('Making charge'))->options(MakingType::class)->default(MakingType::Fixed)->live(),
            // Stored ×100 in both modes: cents for an amount, basis points for a percentage.
            TextInput::make("{$prefix}making_value")->label(__('Making value'))->numeric()->minValue(0)->default(0)->step('0.01')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null || $state === '' ? null : rtrim(rtrim(number_format(((int) $state) / 100, 2, '.', ''), '0'), '.'))
                ->dehydrateStateUsing(fn (mixed $state): int => (int) round(((float) $state) * 100))
                ->helperText(fn (Get $get): string => ($get("{$prefix}making_type") instanceof MakingType ? $get("{$prefix}making_type")->value : $get("{$prefix}making_type")) === MakingType::Percentage->value
                    ? __('Percent of the metal value, e.g. 12.5')
                    : __('Amount, e.g. 25.00')),
            TextInput::make("{$prefix}certificate_number")->label(__('Certificate #')),
        ]);
    }

    private static function byWeight(mixed $mode): bool
    {
        return ($mode instanceof JewelryPricingMode ? $mode : JewelryPricingMode::tryFrom((string) $mode)) === JewelryPricingMode::ByWeight;
    }
}
