<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

/** Two groups: FOOD (Líquidos, DPH, Rancho, PLS, PFT) and NON FOOD (Bazar, Electro, Textil). */
class SectorSeeder extends Seeder
{
    public const SECTORS = [
        ['slug' => 'liquidos', 'name' => 'Líquidos', 'abbr' => 'LIQ', 'group' => 'food'],
        ['slug' => 'dph', 'name' => 'DPH', 'abbr' => 'DPH', 'group' => 'food'],
        ['slug' => 'rancho', 'name' => 'Rancho', 'abbr' => 'RAN', 'group' => 'food'],
        ['slug' => 'pls', 'name' => 'PLS', 'abbr' => 'PLS', 'group' => 'food'],
        ['slug' => 'pft', 'name' => 'PFT', 'abbr' => 'PFT', 'group' => 'food'],
        ['slug' => 'bazar', 'name' => 'Bazar', 'abbr' => 'BAZ', 'group' => 'non_food'],
        ['slug' => 'electro', 'name' => 'Electro', 'abbr' => 'ELE', 'group' => 'non_food'],
        ['slug' => 'textil', 'name' => 'Textil', 'abbr' => 'TEX', 'group' => 'non_food'],
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $i => $sector) {
            Sector::updateOrCreate(['slug' => $sector['slug']], [...$sector, 'position' => $i]);
        }
    }
}
