<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            /* pending | in_progress | adjustments | completed */
            $table->string('status', 15)->default('pending');
            $table->string('priority', 10)->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->double('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['card_id', 'position']);
        });
        Schema::create('subtask_user', function (Blueprint $table) {
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['subtask_id', 'user_id']);
        });
        /* Comments, files and history can hang from a subtask instead of the card itself. */
        Schema::table('card_comments', fn (Blueprint $t) => $t->foreignId('subtask_id')->nullable()->after('card_id')->constrained()->cascadeOnDelete());
        Schema::table('card_attachments', fn (Blueprint $t) => $t->foreignId('subtask_id')->nullable()->after('card_id')->constrained()->cascadeOnDelete());
        Schema::table('card_activities', fn (Blueprint $t) => $t->foreignId('subtask_id')->nullable()->after('card_id')->constrained()->cascadeOnDelete());
    }

    public function down(): void
    {
        Schema::table('card_activities', fn (Blueprint $t) => $t->dropConstrainedForeignId('subtask_id'));
        Schema::table('card_attachments', fn (Blueprint $t) => $t->dropConstrainedForeignId('subtask_id'));
        Schema::table('card_comments', fn (Blueprint $t) => $t->dropConstrainedForeignId('subtask_id'));
        Schema::dropIfExists('subtask_user');
        Schema::dropIfExists('subtasks');
    }
};
