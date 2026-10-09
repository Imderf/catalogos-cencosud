<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* When Ogilvy's clock really started: null while the adjustment waits behind higher-priority work. */
        Schema::table('adjustments', fn (Blueprint $t) => $t->timestamp('agency_started_at')->nullable()->after('received_by_agency_at'));
        /* Whatever already reached Ogilvy keeps running from where it was. */
        DB::table('adjustments')->whereNotNull('received_by_agency_at')->update(['agency_started_at' => DB::raw('stage_since')]);
    }

    public function down(): void
    {
        Schema::table('adjustments', fn (Blueprint $t) => $t->dropColumn('agency_started_at'));
    }
};
