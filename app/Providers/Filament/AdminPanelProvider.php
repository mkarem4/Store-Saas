<?php

namespace App\Providers\Filament;

use App\Models\PlatformSetting;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
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
            ->brandName(fn (): string => (string) PlatformSetting::get('platform_name', config('app.name')).' · '.__('Admin'))
            ->colors(['primary' => Color::Amber])
            ->navigationGroups([__('Stores'), __('Payments'), __('Platform')])
            ->pages([Dashboard::class])
            ->plugins([FilamentShieldPlugin::make()]);
    }
}
