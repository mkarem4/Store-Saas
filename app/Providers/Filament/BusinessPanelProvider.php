<?php

namespace App\Providers\Filament;

use App\Models\Business;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class BusinessPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'business')
            ->passwordReset()
            ->brandName(fn (): string => filament()->getTenant()?->translatedName() ?? config('app.name'))
            ->colors(['primary' => Color::Emerald])
            ->tenant(Business::class, slugAttribute: 'slug')
            ->tenantMenu(false)
            ->navigationGroups([__('Sales'), __('Catalog'), __('Inventory'), __('Vehicles'), __('Rentals'), __('Team'), __('Finance'), __('Settings')])
            ->pages([Dashboard::class]);
    }
}
