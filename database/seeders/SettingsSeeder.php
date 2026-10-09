<?php

namespace Database\Seeders;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Sla\SlaSettings;
use App\Domain\Time\BusinessHours;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $repo = app(SettingsRepository::class);
        if (! Setting::find(SettingsRepository::SLA)) {
            $repo->putSla(new SlaSettings);
        }
        if (! Setting::find(SettingsRepository::BUSINESS_HOURS)) {
            $repo->putBusinessHours(new BusinessHours);
        }
    }
}
