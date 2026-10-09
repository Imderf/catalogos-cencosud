<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sectors are now grouped: FOOD (Líquidos, DPH, Rancho, PLS, PFT) and NON FOOD (Bazar, Electro, Textil).
 * "Food" stops being a sector of its own: whatever was tagged Food becomes every food sector.
 */
return new class extends Migration
{
    private const SECTORS = [
        ['liquidos', 'Líquidos', 'LIQ', 'food'], ['dph', 'DPH', 'DPH', 'food'], ['rancho', 'Rancho', 'RAN', 'food'], ['pls', 'PLS', 'PLS', 'food'], ['pft', 'PFT', 'PFT', 'food'],
        ['bazar', 'Bazar', 'BAZ', 'non_food'], ['electro', 'Electro', 'ELE', 'non_food'], ['textil', 'Textil', 'TEX', 'non_food'],
    ];

    public function up(): void
    {
        Schema::table('sectors', fn (Blueprint $t) => $t->string('group', 12)->default('food')->after('abbr'));
        $now = now();
        foreach (self::SECTORS as $i => [$slug, $name, $abbr, $group]) {
            DB::table('sectors')->updateOrInsert(['slug' => $slug], ['name' => $name, 'abbr' => $abbr, 'group' => $group, 'position' => $i, 'updated_at' => $now, 'created_at' => $now]);
        }
        $food = DB::table('sectors')->where('slug', 'food')->value('id');
        if ($food) {
            $foodIds = DB::table('sectors')->where('group', 'food')->where('id', '!=', $food)->pluck('id');
            foreach ([['page_sector', 'page_id'], ['sector_user', 'user_id']] as [$table, $key]) {
                foreach (DB::table($table)->where('sector_id', $food)->pluck($key) as $owner) {
                    foreach ($foodIds as $sid) {
                        DB::table($table)->updateOrInsert([$key => $owner, 'sector_id' => $sid]);
                    }
                }
                DB::table($table)->where('sector_id', $food)->delete();
            }
            DB::table('sectors')->where('id', $food)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('sectors', fn (Blueprint $t) => $t->dropColumn('group'));
    }
};
