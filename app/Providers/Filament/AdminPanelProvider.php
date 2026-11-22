<?php

namespace App\Providers\Filament;

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
            // The platform console: the shared shell with a monochrome accent.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors(['primary' => self::graphitePalette()])
            ->navigationGroups([NavigationGroup::make(fn (): string => __('Stores')), NavigationGroup::make(fn (): string => __('Payments')), NavigationGroup::make(fn (): string => __('Platform'))])
            // No Shield roles page: only the platform owner can enter this panel and the
            // owner passes every gate, so platform roles would grant nothing. Store roles
            // are provisioned per store (BusinessProvisioner, permissions:sync).
            ->pages([Dashboard::class]);
    }
}
