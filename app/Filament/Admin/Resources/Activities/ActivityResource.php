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
            TextEntry::make('description')->label(__('Event')),
            TextEntry::make('created_at')->label(__('When'))->dateTime(),
            TextEntry::make('causer.name')->label(__('By'))->placeholder(__('System')),
            TextEntry::make('subject_type')->label(__('Record'))->formatStateUsing(fn (?string $state, Activity $record): string => class_basename((string) $state).' #'.$record->subject_id),
            KeyValueEntry::make('properties')->label(__('Details'))->columnSpanFull()
                ->state(fn (Activity $record): array => collect($record->properties?->toArray() ?? [])->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE))->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('When'))->dateTime()->sortable(),
                TextColumn::make('log_name')->label(__('Area'))->badge()->color('gray'),
                TextColumn::make('description')->label(__('Event'))->searchable(),
                TextColumn::make('causer.name')->label(__('By'))->placeholder(__('System')),
                TextColumn::make('subject_type')->label(__('Record'))->formatStateUsing(fn (?string $state, Activity $record): string => class_basename((string) $state).' #'.$record->subject_id),
            ])
            ->filters([
                SelectFilter::make('log_name')->label(__('Area'))->options(fn (): array => Activity::query()->distinct()->pluck('log_name', 'log_name')->filter()->all()),
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
