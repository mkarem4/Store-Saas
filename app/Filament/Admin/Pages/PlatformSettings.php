<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\SettingsPage;
use App\Models\PlatformSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PlatformSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('Platform settings');
    }

    public function getTitle(): string
    {
        return __('Platform settings');
    }

    protected function loadData(): array
    {
        return [
            'platform_name' => PlatformSetting::get('platform_name', config('app.name')),
            'support_email' => PlatformSetting::get('support_email'),
            'default_tax_rate' => PlatformSetting::get('default_tax_rate', PlatformSetting::get('tax_rate', 15)),
            'tax_scope' => PlatformSetting::get('tax_scope', 'all'),
            'commission_rate' => PlatformSetting::get('commission_rate', 3),
            'wallet_hold_days' => PlatformSetting::get('wallet_hold_days', 7),
            'minimum_payout_cents' => PlatformSetting::get('minimum_payout_cents', 0),
        ];
    }

    protected function persist(array $data): void
    {
        foreach ($data as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        PlatformSetting::set('tax_rate', $data['default_tax_rate']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('General'))->columns(2)->schema([
                TextInput::make('platform_name')->label(__('Platform name'))->required(),
                TextInput::make('support_email')->label(__('Support email'))->email(),
            ]),
            Section::make(__('Tax'))->columns(2)->description(__('Regional rates live under Tax rates; this default applies where no rate is set.'))->schema([
                TextInput::make('default_tax_rate')->label(__('Default tax rate'))->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
                Select::make('tax_scope')->label(__('Tax applies to'))->required()->options([
                    'all' => __('POS sales and app orders'),
                    'app_only' => __('App orders only'),
                ]),
            ]),
            Section::make(__('Payments & payouts'))->columns(3)->schema([
                TextInput::make('commission_rate')->label(__('Platform commission'))->numeric()->suffix('%')->minValue(0)->maxValue(100)->required()
                    ->helperText(__('Charged on product value of app orders, excluding tax and delivery.')),
                TextInput::make('wallet_hold_days')->label(__('Hold period (days)'))->integer()->minValue(0)->required()
                    ->helperText(__('Days before a completed order becomes withdrawable.')),
                MoneyInput::make('minimum_payout_cents')->label(__('Minimum payout'))->prefix('USD'),
            ]),
        ]);
    }
}
