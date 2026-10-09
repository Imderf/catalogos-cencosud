<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* A comment that came from the client's review (approved intake) remembers its adjustment. */
        Schema::table('card_comments', fn (Blueprint $t) => $t->foreignId('adjustment_id')->nullable()->after('user_id')->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('card_comments', fn (Blueprint $t) => $t->dropConstrainedForeignId('adjustment_id'));
    }
};
