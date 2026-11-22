<?php

namespace App\Filament\Business\Resources\Employees;

use App\Enums\PayType;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\PayrollService;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Staff (S7, M4.1, M4.10): monthly or hourly pay, optional sales commission
 * for car showrooms and jewelry stores, and a staff-panel login.
 */
class EmployeeResource extends BusinessResource
{
    protected static ?string $model = Employee::class;

    protected static ?string $permission = Permission::ManageEmployees;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Employee');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Employees');
    }

    public static function commissionAllowed(): bool
    {
        return BusinessPanel::isType(PayrollService::COMMISSION_TYPES);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make(__('Employee'))->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required(),
                TextInput::make('position')->label(__('Position')),
                TextInput::make('phone')->label(__('Phone'))->tel(),
                DatePicker::make('hire_date')->label(__('Hire date')),
                Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
            ]),
            Section::make(__('Pay'))->columnSpan(1)->schema([
                Select::make('pay_type')->label(__('Pay type'))->options(PayType::class)->default(PayType::Monthly)->required()->live(),
                MoneyInput::make('monthly_salary_cents')->label(__('Monthly salary'))
                    ->visible(fn (Get $get): bool => self::payType($get('pay_type')) === PayType::Monthly)->required(fn (Get $get): bool => self::payType($get('pay_type')) === PayType::Monthly),
                MoneyInput::make('hourly_rate_cents')->label(__('Hourly rate'))
                    ->visible(fn (Get $get): bool => self::payType($get('pay_type')) === PayType::Hourly)->required(fn (Get $get): bool => self::payType($get('pay_type')) === PayType::Hourly),
                TextInput::make('commission_rate_bps')->label(__('Sales commission'))->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%')
                    ->visible(self::commissionAllowed())
                    ->helperText(__('Percent of this employee\'s sales, net of returns.'))
                    ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : rtrim(rtrim(number_format(((int) $state) / 100, 2, '.', ''), '0'), '.'))
                    ->dehydrateStateUsing(fn (mixed $state): int => (int) round(((float) $state) * 100)),
                Toggle::make('is_active')->label(__('Active'))->default(true),
            ]),
            Section::make(__('Staff panel sign-in'))->columnSpanFull()->columns(2)->visibleOn('create')
                ->description(__('Optional. The employee can clock in, sell and see their hours.'))
                ->schema([
                    TextInput::make('login_email')->label(__('Email'))->email()->unique(User::class, 'email')->dehydrated(false),
                    TextInput::make('login_password')->label(__('Temporary password'))->default(fn (): string => Str::password(10, symbols: false))
                        ->required(fn (Get $get): bool => filled($get('login_email')))->minLength(8)->dehydrated(false),
                ]),
        ]);
    }

    private static function payType(mixed $state): ?PayType
    {
        return $state instanceof PayType ? $state : PayType::tryFrom((string) $state);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->description(fn (Employee $record): ?string => $record->position),
                TextColumn::make('pay_type')->label(__('Pay'))->badge()->color('gray'),
                TextColumn::make('rate')->label(__('Rate'))->state(fn (Employee $record): int => $record->pay_type === PayType::Hourly ? (int) $record->hourly_rate_cents : (int) $record->monthly_salary_cents)
                    ->money($currency, divideBy: 100),
                TextColumn::make('commission_rate_bps')->label(__('Commission'))->visible(self::commissionAllowed())
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format(((int) $state) / 100, 2), '0'), '.').'%'),
                TextColumn::make('user.email')->label(__('Sign-in'))->placeholder(__('None')),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->filters([TernaryFilter::make('is_active')->label(__('Active'))->default(true)])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('login')->label(fn (Employee $record): string => $record->user ? __('Reset sign-in') : __('Give sign-in'))
                        ->icon(Heroicon::OutlinedKey)
                        ->fillForm(fn (Employee $record): array => ['email' => $record->user?->email ?? $record->email, 'password' => Str::password(10, symbols: false)])
                        ->schema(fn (Employee $record): array => [
                            TextInput::make('email')->label(__('Email'))->email()->required()->unique(User::class, 'email', ignorable: $record->user),
                            TextInput::make('password')->label(__('Temporary password'))->required()->minLength(8),
                        ])
                        ->action(function (Employee $record, array $data): void {
                            app(EmployeeService::class)->grantLogin($record, $data['email'], $data['password']);
                            Notification::make()->success()->persistent()->title(__('Sign-in ready'))
                                ->body(__(':email / :password — they choose a new password at first sign-in.', ['email' => $data['email'], 'password' => $data['password']]))->send();
                        }),
                    Action::make('toggle')->label(fn (Employee $record): string => $record->is_active ? __('Deactivate') : __('Activate'))
                        ->icon(Heroicon::OutlinedPower)->color(fn (Employee $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->action(fn (Employee $record) => app(EmployeeService::class)->setActive($record, ! $record->is_active)),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
