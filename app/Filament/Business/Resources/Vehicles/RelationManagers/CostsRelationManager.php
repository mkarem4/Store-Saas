<?php

namespace App\Filament\Business\Resources\Vehicles\RelationManagers;

use App\Enums\VehicleStatus;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\VehicleCost;
use App\Services\VehicleService;
use App\Support\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Maintenance and preparation costs; they add to the car's total cost and
 * never count as general expenses (M5.4).
 */
class CostsRelationManager extends RelationManager
{
    protected static string $relationship = 'costs';

    protected static function getModelLabel(): ?string
    {
        return __('cost');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('costs');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Maintenance & costs');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('description')->label(__('Description'))->required(),
            MoneyInput::make('amount_cents')->label(__('Amount'))->required(),
            DatePicker::make('incurred_at')->label(__('Date'))->default(now())->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();
        $vehicle = $this->getOwnerRecord();

        return $table
            ->heading(__('Total cost: :amount', ['amount' => Money::format($vehicle->totalCostCents(), $currency)]))
            ->defaultSort('incurred_at', 'desc')
            ->columns([
                TextColumn::make('incurred_at')->label(__('Date'))->date(),
                TextColumn::make('description')->label(__('Description')),
                TextColumn::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $vehicle->status !== VehicleStatus::Sold)
                    ->using(fn (array $data): VehicleCost => app(VehicleService::class)->addCost($vehicle, (int) $data['amount_cents'], $data['description'], Carbon::parse($data['incurred_at']), auth()->id())),
            ])
            ->recordActions([DeleteAction::make()->visible(fn (): bool => $vehicle->status !== VehicleStatus::Sold)]);
    }
}
