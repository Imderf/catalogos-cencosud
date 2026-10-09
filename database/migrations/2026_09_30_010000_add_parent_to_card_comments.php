<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Replies: a comment can answer another one (one level). If the parent goes, the reply stays on its own. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_comments', fn (Blueprint $t) => $t->foreignId('parent_id')->nullable()->after('subtask_id')->constrained('card_comments')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('card_comments', fn (Blueprint $t) => $t->dropConstrainedForeignId('parent_id'));
    }
};
