<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', fn (Blueprint $t) => $t->date('start_date')->nullable()->after('priority'));
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $t) => $t->dropColumn('start_date'));
    }
};
