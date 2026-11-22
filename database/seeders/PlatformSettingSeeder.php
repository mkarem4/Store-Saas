<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::set('platform_name', config('app.name'));
        PlatformSetting::set('support_email', 'support@store.test');

        // D6/D31: default until the client's per-state tax file arrives.
        PlatformSetting::set('default_tax_rate', 15);
        PlatformSetting::set('tax_rate', 15);
        // G2: tax applies to POS and app orders (22/11 answer).
        PlatformSetting::set('tax_scope', 'all');

        // D26 / G3 / G4 working assumptions.
        PlatformSetting::set('commission_rate', 3);
        PlatformSetting::set('wallet_hold_days', 7);
        PlatformSetting::set('minimum_payout_cents', 0);
    }
}
