<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* 'instant': one email per movement. 'daily': the in-app inbox always, plus one summary email each working morning. */
        Schema::table('users', fn (Blueprint $t) => $t->string('notification_mode', 10)->default('instant')->after('must_change_password'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_mode'));
    }
};
