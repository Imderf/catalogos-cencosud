<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            /* extend: everyone works from–to that day (clocks run). closed: nobody works (holiday). users: only the listed people may act from–to. */
            $table->string('kind', 10);
            $table->unsignedSmallInteger('from_minutes')->nullable();
            $table->unsignedSmallInteger('to_minutes')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('date');
        });
        Schema::create('schedule_exception_user', function (Blueprint $table) {
            $table->foreignId('schedule_exception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['schedule_exception_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exception_user');
        Schema::dropIfExists('schedule_exceptions');
    }
};
