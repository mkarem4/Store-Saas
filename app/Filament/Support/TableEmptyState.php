<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * One empty state for every table that tells "nothing here yet" apart from
 * "nothing matches your search or filters". A list page can describe its own
 * empty tabs by defining tabEmptyState(): ?array{0: string, 1?: string}.
 */
final class TableEmptyState
{
    public static function apply(Table $table): Table
    {
        return $table
            ->emptyStateHeading(function (Table $table): string {
                if (self::isFiltered($table)) {
                    return __('No matches');
                }

                return self::forTab($table)[0] ?? __('filament-tables::table.empty.heading', ['model' => $table->getPluralModelLabel()]);
            })
            ->emptyStateDescription(fn (Table $table): ?string => self::isFiltered($table)
                ? __('Nothing matches the current search or filters.')
                : (self::forTab($table)[1] ?? null))
            ->emptyStateIcon(fn (Table $table): Heroicon => self::isFiltered($table) ? Heroicon::OutlinedMagnifyingGlass : Heroicon::OutlinedInbox)
            ->emptyStateActions([
                Action::make('clearFilters')
                    ->label(__('Clear search and filters'))
                    ->color('gray')
                    ->visible(fn (Table $table): bool => self::isFiltered($table))
                    ->action(fn (Table $table) => $table->getLivewire()->removeTableFilters()),
            ]);
    }

    public static function isFiltered(Table $table): bool
    {
        $livewire = $table->getLivewire();

        return (method_exists($livewire, 'hasTableSearch') && $livewire->hasTableSearch())
            || $table->getActiveFiltersCount() > 0;
    }

    /**
     * @return array<int, string>
     */
    private static function forTab(Table $table): array
    {
        $livewire = $table->getLivewire();

        return method_exists($livewire, 'tabEmptyState') ? ($livewire->tabEmptyState() ?? []) : [];
    }
}
