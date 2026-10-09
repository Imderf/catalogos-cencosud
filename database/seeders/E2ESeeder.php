<?php

namespace Database\Seeders;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Time\BusinessHours;
use Illuminate\Database\Seeder;

/** End-to-end runs must be able to act at any hour: business hours become 24/7. */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        app(SettingsRepository::class)->putBusinessHours(new BusinessHours([1, 2, 3, 4, 5, 6, 7], 0.0, 24.0));
    }
}
