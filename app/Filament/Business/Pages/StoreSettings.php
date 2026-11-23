<?php

namespace App\Filament\Business\Pages;

use App\Enums\AttendanceMode;
use App\Enums\BusinessTypeKey;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\Fields;
use App\Filament\Support\SettingsPage;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Store settings (M7.3, D15): app ordering, delivery by distance, opening
 * hours, map location and the in-store attendance rule.
 */
class StoreSettings extends SettingsPage
{
    // Settings read best at a comfortable width, not stretched across a wide screen.
    protected Width|string|null $maxContentWidth = Width::FiveExtraLarge;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function getNavigationLabel(): string
    {
        return __('Store settings');
    }

    public function getTitle(): string
    {
        return __('Store settings');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::can(Permission::ManageSettings);
    }

    protected function loadData(): array
    {
        $business = BusinessPanel::business();
        $hours = [];

        foreach (self::DAYS as $day) {
            $value = $business->settings("working_hours.{$day}");
            $hours[$day] = ['open_day' => filled($value), 'open' => $value['open'] ?? '09:00', 'close' => $value['close'] ?? '21:00'];
        }

        return [
            'name' => ['en' => $business->translated('name', 'en'), 'ar' => $business->translated('name', 'ar')],
            'phone' => $business->phone,
            'email' => $business->email,
            'website' => $business->website,
            'address_line' => $business->address_line,
            'city' => $business->city,
            'postal_code' => $business->postal_code,
            'pickup_enabled' => (bool) $business->settings('pickup_enabled'),
            'delivery_enabled' => (bool) $business->settings('delivery_enabled'),
            'minimum_order_cents' => (int) $business->settings('minimum_order_cents', 0),
            'accept_timeout_minutes' => (int) $business->settings('accept_timeout_minutes', 10),
            'orders_paused' => (bool) $business->settings('orders_paused'),
            'delivery_base_fee_cents' => (int) $business->settings('delivery_base_fee_cents', 0),
            'delivery_fee_per_km_cents' => (int) $business->settings('delivery_fee_per_km_cents', 0),
            'delivery_max_km' => (float) $business->settings('delivery_max_km', 10),
            'allow_negative_stock' => (bool) $business->settings('allow_negative_stock'),
            'gold_auto_price' => (bool) $business->settings('gold_auto_price', true),
            'working_hours' => $hours,
            'latitude' => $business->latitude,
            'longitude' => $business->longitude,
            'attendance_mode' => $business->attendance_mode ?? AttendanceMode::Ip,
            'allowed_ips' => $business->allowed_ips ?? [],
            'geofence_radius_m' => $business->geofence_radius_m,
        ];
    }

    protected function persist(array $data): void
    {
        abort_unless(static::canAccess(), 403);

        $business = BusinessPanel::business();
        $data += $this->loadData();
        $hours = [];

        foreach (self::DAYS as $day) {
            $row = $data['working_hours'][$day] ?? [];
            $hours[$day] = ($row['open_day'] ?? false) ? ['open' => substr((string) $row['open'], 0, 5), 'close' => substr((string) $row['close'], 0, 5)] : null;
        }

        $business->fill([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'website' => $data['website'],
            'address_line' => $data['address_line'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'attendance_mode' => $data['attendance_mode'],
            'allowed_ips' => array_values(array_filter(array_map('trim', $data['allowed_ips'] ?? []))),
            'geofence_radius_m' => (int) $data['geofence_radius_m'],
        ]);

        $business->settings = array_merge($business->settings ?? [], [
            'pickup_enabled' => (bool) $data['pickup_enabled'],
            'delivery_enabled' => (bool) $data['delivery_enabled'],
            'minimum_order_cents' => (int) $data['minimum_order_cents'],
            'accept_timeout_minutes' => (int) $data['accept_timeout_minutes'],
            'orders_paused' => (bool) $data['orders_paused'],
            'delivery_base_fee_cents' => (int) $data['delivery_base_fee_cents'],
            'delivery_fee_per_km_cents' => (int) $data['delivery_fee_per_km_cents'],
            'delivery_max_km' => (float) $data['delivery_max_km'],
            'allow_negative_stock' => (bool) ($data['allow_negative_stock'] ?? false),
            'gold_auto_price' => (bool) ($data['gold_auto_price'] ?? true),
            'working_hours' => $hours,
        ]);

        $business->save();

        activity('settings')->performedOn($business)->causedBy(auth()->user())->log('store.settings_updated');
    }

    public function form(Schema $schema): Schema
    {
        $type = BusinessPanel::typeKey();
        $sellsInApp = ! in_array($type, [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value], true);
        $days = ['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday'), 'sat' => __('Saturday'), 'sun' => __('Sunday')];
        $ipMode = fn (Get $get): bool => in_array(($get('attendance_mode') instanceof AttendanceMode ? $get('attendance_mode') : AttendanceMode::tryFrom((string) $get('attendance_mode'))), [AttendanceMode::Ip, AttendanceMode::Both, AttendanceMode::Either], true);
        $gpsMode = fn (Get $get): bool => in_array(($get('attendance_mode') instanceof AttendanceMode ? $get('attendance_mode') : AttendanceMode::tryFrom((string) $get('attendance_mode'))), [AttendanceMode::Gps, AttendanceMode::Both, AttendanceMode::Either], true);

        return $schema->components([
            Section::make(__('Store profile'))->aside()->columns(2)
                ->description(__('Update your store name and contact details.'))
                ->schema([
                    Fields::translatable('name', __('Store name')),
                    TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(255),
                    TextInput::make('email')->label(__('Email'))->email()->maxLength(255),
                    TextInput::make('website')->label(__('Website'))->url()->maxLength(255)->columnSpanFull(),
                    TextInput::make('address_line')->label(__('Address'))->maxLength(255)->columnSpanFull(),
                    TextInput::make('city')->label(__('City'))->maxLength(255),
                    TextInput::make('postal_code')->label(__('Postal code'))->maxLength(255),
                ]),
            Section::make(__('App orders'))->aside()->description(__('How customers order from you in the app.'))->columns(2)->visible($sellsInApp)->schema([
                Toggle::make('pickup_enabled')->label(__('Pickup')),
                Toggle::make('delivery_enabled')->label(__('Delivery'))->live(),
                Toggle::make('orders_paused')->label(__('Pause new orders')),
                MoneyInput::make('minimum_order_cents')->label(__('Minimum order')),
                TextInput::make('accept_timeout_minutes')->label(__('Minutes to accept an order'))->integer()->minValue(2)->maxValue(60)->required()
                    ->helperText(__('Unaccepted orders expire and the card hold is released.')),
            ]),
            Section::make(__('Delivery fee by distance'))->aside()->description(__('A base fee plus a price for each kilometre from the store.'))->columns(2)->visible(fn (Get $get): bool => $sellsInApp && (bool) $get('delivery_enabled'))->schema([
                MoneyInput::make('delivery_base_fee_cents')->label(__('Base fee')),
                MoneyInput::make('delivery_fee_per_km_cents')->label(__('Price per km'))->required(),
                TextInput::make('delivery_max_km')->label(__('Maximum distance'))->numeric()->minValue(0)->suffix('km')
                    ->helperText(__('0 = no limit.')),
            ]),
            Section::make(__('Opening hours'))->aside()->description(__('The app only takes orders while you are open.'))->visible($sellsInApp)->schema(array_map(
                fn (string $day, string $label) => Grid::make(['default' => 1, 'sm' => 3])->schema([
                    Toggle::make("working_hours.{$day}.open_day")->label($label)->inline(false)->live(),
                    TimePicker::make("working_hours.{$day}.open")->label(__('Opens'))->seconds(false)->visible(fn (Get $get): bool => (bool) $get("working_hours.{$day}.open_day")),
                    TimePicker::make("working_hours.{$day}.close")->label(__('Closes'))->seconds(false)->visible(fn (Get $get): bool => (bool) $get("working_hours.{$day}.open_day")),
                ]),
                array_keys($days),
                $days,
            )),
            Section::make(__('Store location'))->aside()->columns(2)
                ->description(__('Used to compute delivery distance and for the attendance geofence.'))
                ->headerActions([
                    Action::make('locate')->label(__('Use my current location'))->icon(Heroicon::OutlinedMapPin)->color('gray')
                        ->alpineClickHandler("navigator.geolocation.getCurrentPosition((p) => { \$wire.set('data.latitude', p.coords.latitude.toFixed(7)); \$wire.set('data.longitude', p.coords.longitude.toFixed(7)); })"),
                ])
                ->schema([
                    TextInput::make('latitude')->label(__('Latitude'))->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label(__('Longitude'))->numeric()->minValue(-180)->maxValue(180),
                ]),
            Section::make(__('Attendance'))->aside()->columns(1)
                ->description(__('Staff can only clock in from inside the store.'))
                ->schema([
                    Select::make('attendance_mode')->label(__('Check'))->options(AttendanceMode::class)->required()->live(),
                    TagsInput::make('allowed_ips')->label(__('Store network IPs'))->placeholder('203.0.113.10 or 10.0.0.0/24')->visible($ipMode)
                        ->helperText(__('Your current IP: :ip', ['ip' => request()->ip()]))
                        ->hintAction(Action::make('addMyIp')->label(__('Add my IP'))->action(function (Get $get, Set $set): void {
                            $set('allowed_ips', array_values(array_unique([...($get('allowed_ips') ?? []), (string) request()->ip()])));
                        })),
                    TextInput::make('geofence_radius_m')->label(__('Allowed radius'))->integer()->minValue(10)->maxValue(5000)->suffix('m')->visible($gpsMode),
                ]),
            Section::make(__('Inventory'))->aside()->description(__('What happens when an item runs out.'))->visible(! in_array($type, [BusinessTypeKey::CarShowroom->value, BusinessTypeKey::CarRental->value, BusinessTypeKey::Restaurant->value], true))->schema([
                Toggle::make('allow_negative_stock')->label(__('Allow selling below zero stock')),
            ]),
            Section::make(__('Gold price'))->aside()->description(__('Where today\'s gold price comes from.'))->visible($type === BusinessTypeKey::Jewelry->value)->schema([
                Toggle::make('gold_auto_price')->label(__('Update gold prices automatically')),
            ]),
        ]);
    }
}
