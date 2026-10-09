<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Client;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $cencosud = Client::updateOrCreate(['name' => 'Cencosud'], ['color' => '#0057B8', 'is_active' => true]);
        foreach ([['jumbo', 'Jumbo'], ['metro', 'Metro']] as [$slug, $name]) {
            Brand::updateOrCreate(['slug' => $slug], ['name' => $name, 'client_id' => $cencosud->id]);
        }
    }
}
