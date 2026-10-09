<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            /* Deliverable type: octavilla | cuartilla | volante */
            $table->string('kind', 20)->default('octavilla')->after('name');
        });
        Schema::table('pages', function (Blueprint $table) {
            /* Null while Ogilvy prepares the page; set when it is sent to Marketing and the chain starts. */
            $table->timestamp('submitted_at')->nullable()->after('current_version');
        });
        /* Everything that already exists was in review: consider it sent when it was created. */
        DB::table('pages')->whereNull('submitted_at')->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('catalogs', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('submitted_at'));
    }
};
