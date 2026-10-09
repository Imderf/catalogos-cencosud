<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            /* validation: the ascending "Validar" pass. print: the descending "Aprobar a imprenta" pass. */
            $table->string('phase', 12)->default('validation')->after('role');
            $table->dropUnique(['page_id', 'version', 'role']);
            $table->unique(['page_id', 'version', 'role', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->dropUnique(['page_id', 'version', 'role', 'phase']);
            $table->unique(['page_id', 'version', 'role']);
            $table->dropColumn('phase');
        });
    }
};
