<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjustments', function (Blueprint $table) {
            /* Last deadline alert sent for the current stage: null, 'urgente' or 'vencido'. */
            $table->string('alert_level', 10)->nullable()->after('received_by_agency_at');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->string('approval_alert_level', 10)->nullable()->after('waiting_since');
        });
    }

    public function down(): void
    {
        Schema::table('adjustments', fn (Blueprint $t) => $t->dropColumn('alert_level'));
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn('approval_alert_level'));
    }
};
