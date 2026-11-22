<?php

namespace App\Filament\Admin\Resources\Businesses;

use App\Enums\BusinessStatus;
use App\Filament\Admin\Resources\Businesses\Pages\CreateBusiness;
use App\Filament\Admin\Resources\Businesses\Pages\EditBusiness;
use App\Filament\Admin\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Support\Fields;
use App\Models\Business;
use App\Models\BusinessType;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Stores';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('Store');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Stores');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Store'))->columns(2)->schema([
                Select::make('business_type_id')
                    ->label(__('Business type'))
                    ->options(fn (): array => BusinessType::query()->where('is_active', true)->orderBy('sort_order')->get()
                        ->mapWithKeys(fn (BusinessType $type): array => [$type->id => $type->translatedName()])->all())
                    ->required()
                    ->disabledOn('edit')
                    ->helperText(__('The type sets the screens and fields the store gets. It cannot change later.')),
                Select::make('status')->label(__('Status'))->options(BusinessStatus::class)->default(BusinessStatus::Active)->required(),
                Fields::translatable('name', __('Store name')),
                Fields::translatable('description', __('Description'), required: false, textarea: true),
                SpatieMediaLibraryFileUpload::make('logo')->label(__('Logo'))->collection('logo')->image()->imageEditor(),
                SpatieMediaLibraryFileUpload::make('cover')->label(__('Cover image'))->collection('cover')->image(),
            ]),
            Section::make(__('Contact & location'))->columns(2)->schema([
                TextInput::make('phone')->label(__('Phone'))->tel(),
                TextInput::make('email')->label(__('Email'))->email(),
                TextInput::make('website')->label(__('Website'))->url(),
                TextInput::make('address_line')->label(__('Address'))->columnSpanFull(),
                TextInput::make('city')->label(__('City')),
                Select::make('country')->label(__('Country'))->options(['US' => __('United States'), 'JO' => __('Jordan')])->default('US')->required()->live(),
                TextInput::make('state')->label(__('State / region'))
                    ->datalist(Fields::usStates())
                    ->helperText(__('Sets the sales tax rate.')),
                TextInput::make('postal_code')->label(__('Postal code')),
                TextInput::make('latitude')->label(__('Latitude'))->numeric()->minValue(-90)->maxValue(90)
                    ->helperText(__('Used for delivery distance and the attendance geofence.')),
                TextInput::make('longitude')->label(__('Longitude'))->numeric()->minValue(-180)->maxValue(180),
                Select::make('timezone')->label(__('Timezone'))->options(Fields::timezones())->searchable()->default('America/New_York')->required(),
                Select::make('currency')->label(__('Currency'))->options(['USD' => 'USD', 'JOD' => 'JOD'])->default('USD')->required(),
            ]),
            Section::make(__('Store owner account'))
                ->columnSpanFull()
                ->description(__('The owner signs in to the store dashboard with this email and the temporary password, then chooses their own.'))
                ->columns(3)
                ->visibleOn('create')
                ->schema([
                    TextInput::make('owner_name')->label(__('Owner name'))->required()->dehydrated(false),
                    TextInput::make('owner_email')->label(__('Owner email'))->email()->required()->dehydrated(false)
                        ->unique(User::class, 'email'),
                    TextInput::make('owner_password')->label(__('Temporary password'))->required()->dehydrated(false)
                        ->default(fn (): string => Str::password(12, symbols: false))->minLength(8),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')->collection('logo')->circular()->label(''),
                TextColumn::make('name')->label(__('Store'))
                    ->state(fn (Business $record): string => $record->translatedName())
                    ->description(fn (Business $record): string => $record->slug)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")),
                TextColumn::make('businessType.name')->label(__('Type'))
                    ->state(fn (Business $record): string => (string) $record->businessType?->translatedName())->badge()->color('gray'),
                TextColumn::make('state')->label(__('Region'))->formatStateUsing(fn (Business $record): string => trim($record->city.', '.$record->state.' '.$record->country, ', ')),
                TextColumn::make('tax')->label(__('Tax'))->state(fn (Business $record): string => rtrim(rtrim(number_format($record->taxRate(), 3), '0'), '.').'%'),
                TextColumn::make('owner.email')->label(__('Owner'))->searchable(),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('created_at')->label(__('Created'))->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(BusinessStatus::class),
                SelectFilter::make('business_type_id')->label(__('Type'))->relationship('businessType', 'key'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('impersonate')
                        ->label(__('Sign in as owner'))
                        ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                        ->visible(fn (Business $record): bool => $record->owner !== null && $record->isActive())
                        ->url(fn (Business $record): string => route('impersonate.start', $record->owner)),
                    Action::make('suspend')
                        ->label(__('Suspend'))
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription(__('The store disappears from the app and its staff can no longer sign in.'))
                        ->visible(fn (Business $record): bool => $record->isActive())
                        ->action(fn (Business $record) => $record->update(['status' => BusinessStatus::Suspended])),
                    Action::make('activate')
                        ->label(__('Activate'))
                        ->icon(Heroicon::OutlinedPlayCircle)
                        ->color('success')
                        ->visible(fn (Business $record): bool => ! $record->isActive())
                        ->action(fn (Business $record) => $record->update(['status' => BusinessStatus::Active])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit' => EditBusiness::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
