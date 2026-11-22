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
            ->brandLogo(fn () => view('filament.admin.brand'))
            // The platform console, after Linear / Vercel: its own theme, monochrome chrome,
            // Geist for Latin with Readex Pro for Arabic, and no topbar.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font("Geist', 'Readex Pro", url: 'https://fonts.bunny.net/css?family=geist:400,500,600|readex-pro:400,500,600&display=swap')
            ->colors(['primary' => self::graphitePalette(), 'gray' => Color::Zinc])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->topbar(false)
            ->navigationGroups([NavigationGroup::make(fn (): string => __('Stores')), NavigationGroup::make(fn (): string => __('Payments')), NavigationGroup::make(fn (): string => __('Platform'))])
            ->pages([Dashboard::class])
            ->plugins([FilamentShieldPlugin::make()->navigationGroup(fn (): string => __('Platform'))]);
    }
}
