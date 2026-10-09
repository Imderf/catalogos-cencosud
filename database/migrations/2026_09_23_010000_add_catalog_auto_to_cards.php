<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* true: the card was created for the catalog and goes away with it; false: an existing task adopted the catalog. */
        Schema::table('cards', fn (Blueprint $t) => $t->boolean('catalog_auto')->default(true)->after('catalog_id'));
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $t) => $t->dropColumn('catalog_auto'));
    }
};
