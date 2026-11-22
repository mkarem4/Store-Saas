<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        PlatformSetting::set('platform_name', 'Store Platform');
        PlatformSetting::set('tax_rate', 15);
        PlatformSetting::set('tax_scope', 'app_only');
        PlatformSetting::set('support_email', 'support@store.test');
    }
}
