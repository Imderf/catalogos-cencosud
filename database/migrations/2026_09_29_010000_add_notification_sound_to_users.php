<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Play a short sound in the browser when a notification arrives. Each person can turn it off in Mi cuenta. */
        Schema::table('users', fn (Blueprint $t) => $t->boolean('notification_sound')->default(true)->after('notification_mode'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notification_sound'));
    }
};
