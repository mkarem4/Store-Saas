<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'admin')
            ->default()
            ->brandName(fn (): string => __('Yulio'))
            // The platform console: its own theme, type and colors, and a full-height rail instead of a topbar.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Readex Pro')
            ->colors(['primary' => self::tealPalette(), 'gray' => Color::Slate])
            ->topbar(false)
            ->navigationGroups([NavigationGroup::make(fn (): string => __('Stores')), NavigationGroup::make(fn (): string => __('Payments')), NavigationGroup::make(fn (): string => __('Platform'))])
            ->pages([Dashboard::class])
            ->plugins([FilamentShieldPlugin::make()->navigationGroup(fn (): string => __('Platform'))]);
    }
}
