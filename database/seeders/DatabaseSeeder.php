<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SectorSeeder::class,
            BrandSeeder::class,
            SettingsSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
