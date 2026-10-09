<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* A subtask may stand for one page of a linked catalog; the review then drives its status. */
        Schema::table('subtasks', fn (Blueprint $t) => $t->foreignId('page_id')->nullable()->after('card_id')->constrained()->nullOnDelete());

        /* Every client comment on a page lands here; a moderator lets it in (deliverable) or dismisses it. */
        Schema::create('adjustment_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjustment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('pending'); /* pending | approved | dismissed */
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique('adjustment_id');
        });

        /* Work Ogilvy commits to on a page: from an approved comment, or added by hand. */
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intake_id')->nullable()->constrained('adjustment_intakes')->nullOnDelete();
            $table->string('title', 300);
            $table->string('status', 12)->default('pending'); /* pending | in_progress | done */
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->double('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('adjustment_intakes');
        Schema::table('subtasks', fn (Blueprint $t) => $t->dropConstrainedForeignId('page_id'));
    }
};
