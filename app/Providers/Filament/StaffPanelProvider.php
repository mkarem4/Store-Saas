<?php

namespace App\Providers\Filament;

use App\Filament\Staff\Pages\TimeClock;
use App\Models\Business;
use Filament\Panel;
use Filament\PanelProvider;

class StaffPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->base($panel, 'staff')
            ->brandName(fn (): string => filament()->getTenant()?->translatedName() ?? config('app.name'))
            ->tenant(Business::class, slugAttribute: 'slug')
            ->tenantMenu(false)
            ->homeUrl(fn (): string => TimeClock::getUrl());
    }
}
