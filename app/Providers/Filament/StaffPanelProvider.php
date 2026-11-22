<?php

namespace App\Providers\Filament;

use App\Filament\Staff\Pages\TimeClock;
use App\Models\Business;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class StaffPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'staff')
            ->brandName(fn (): string => filament()->getTenant()?->translatedName() ?? config('app.name'))
            ->colors(['primary' => Color::Sky])
            ->tenant(Business::class, slugAttribute: 'slug')
            ->tenantMenu(false)
            ->homeUrl(fn (): string => TimeClock::getUrl());
    }
}
