<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** An adjustment can be of several kinds at once (price and image, say). `type` stays as the first one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjustments', fn (Blueprint $t) => $t->json('types')->nullable()->after('type'));
        DB::table('adjustments')->whereNull('types')->update(['types' => DB::raw('json_build_array(type)')]);
    }

    public function down(): void
    {
        Schema::table('adjustments', fn (Blueprint $t) => $t->dropColumn('types'));
    }
};
