<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Audit log (rule 9, M1.6): impersonation, price/attendance/refund/payroll
 * changes and other sensitive actions.
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('Audit entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Audit log');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('description')->label(__('Event'))->formatStateUsing(fn (?string $state): string => self::event($state)),
            TextEntry::make('created_at')->label(__('When'))->dateTime(),
            TextEntry::make('causer.name')->label(__('By'))->placeholder(__('System')),
            TextEntry::make('subject_type')->label(__('Affected item'))->formatStateUsing(fn (?string $state, Activity $record): string => self::subject($state).' #'.$record->subject_id),
            KeyValueEntry::make('properties')->label(__('Details'))->columnSpanFull()
                ->state(fn (Activity $record): array => collect($record->properties?->toArray() ?? [])->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE))->all()),
        ]);
    }

    /** Event keys like "vehicle.sold" read as words; unknown ones fall back to a headline. */
    public static function event(?string $key): string
    {
        return Lang::has("activity.events.{$key}") ? __("activity.events.{$key}") : Str::headline((string) $key);
    }

    public static function area(?string $key): string
    {
        return Lang::has("activity.areas.{$key}") ? __("activity.areas.{$key}") : Str::headline((string) $key);
    }

    /** "App\Models\Business" becomes "Store" instead of a class name. */
    public static function subject(?string $class): string
    {
        $base = class_basename((string) $class);

        return Lang::has("activity.subjects.{$base}") ? __("activity.subjects.{$base}") : Str::headline($base);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime()->sortable(),
                TextColumn::make('log_name')->label(__('Area'))->badge()->color('gray')->formatStateUsing(fn (?string $state): string => self::area($state)),
                TextColumn::make('description')->label(__('Event'))->searchable()->formatStateUsing(fn (?string $state): string => self::event($state)),
                TextColumn::make('causer.name')->label(__('By'))->placeholder(__('System')),
                TextColumn::make('subject_type')->label(__('Affected item'))->formatStateUsing(fn (?string $state, Activity $record): string => self::subject($state).' #'.$record->subject_id),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('Area'))->options(fn (): array => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->map(fn (string $name): string => self::area($name))->all()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListActivities::route('/')];
    }
}
