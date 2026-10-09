<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_versions', function (Blueprint $table) {
            /* Screen-sized copy and thumbnail, generated in the queue; null until processed. */
            $table->string('display_path')->nullable()->after('image_path');
            $table->string('thumb_path')->nullable()->after('display_path');
        });
    }

    public function down(): void
    {
        Schema::table('page_versions', fn (Blueprint $t) => $t->dropColumn(['display_path', 'thumb_path']));
    }
};
