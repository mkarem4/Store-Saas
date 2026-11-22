<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'admin')
            ->default()
            ->brandName(fn (): string => __('Yulio'))
            ->navigationGroups([NavigationGroup::make(fn (): string => __('Stores')), NavigationGroup::make(fn (): string => __('Payments')), NavigationGroup::make(fn (): string => __('Platform'))])
            ->pages([Dashboard::class])
            ->plugins([FilamentShieldPlugin::make()]);
    }
}
