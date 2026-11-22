<?php

namespace App\Filament\Staff\Pages;

use App\Filament\Pos\PointOfSalePage;
use App\Filament\Staff\StaffPanel;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class PointOfSale extends PointOfSalePage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pos';

    public static function canAccess(): bool
    {
        return parent::canAccess() && StaffPanel::employee() !== null;
    }

    public function completeSale(array $data): void
    {
        // Staff sales are always attributed to the signed-in employee (M3.6).
        $this->employeeId = StaffPanel::employee()?->id;

        if ($this->employeeId === null) {
            Notification::make()->danger()->title(__('Your account is not linked to an employee.'))->send();

            return;
        }

        parent::completeSale($data);
    }
}
