<?php

namespace App\Providers\Filament;

use App\Models\Business;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class BusinessPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'business')
            ->passwordReset()
            ->brandName(fn (): string => filament()->getTenant()?->translatedName() ?? config('app.name'))
            ->tenant(Business::class, slugAttribute: 'slug')
            ->tenantMenu(false)
            ->navigationGroups([NavigationGroup::make(fn (): string => __('Sales')), NavigationGroup::make(fn (): string => __('Catalog')), NavigationGroup::make(fn (): string => __('Inventory')), NavigationGroup::make(fn (): string => __('Vehicles')), NavigationGroup::make(fn (): string => __('Rentals')), NavigationGroup::make(fn (): string => __('Finance')), NavigationGroup::make(fn (): string => __('Team')), NavigationGroup::make(fn (): string => __('Settings'))])
            ->pages([Dashboard::class]);
    }
}
