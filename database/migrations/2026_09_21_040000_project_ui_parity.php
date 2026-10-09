<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_attachments', function (Blueprint $table) {
            /* An attachment may belong to a comment or be the value of an image field; otherwise it is the card's own. */
            $table->foreignId('card_comment_id')->nullable()->after('card_id')->constrained('card_comments')->cascadeOnDelete();
            $table->foreignId('custom_field_id')->nullable()->after('card_comment_id')->constrained('custom_fields')->cascadeOnDelete();
        });
        Schema::table('custom_fields', fn (Blueprint $t) => $t->boolean('required')->default(false)->after('options'));
        Schema::table('clients', fn (Blueprint $t) => $t->string('logo_path')->nullable()->after('color'));
    }

    public function down(): void
    {
        Schema::table('card_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_comment_id');
            $table->dropConstrainedForeignId('custom_field_id');
        });
        Schema::table('custom_fields', fn (Blueprint $t) => $t->dropColumn('required'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('logo_path'));
    }
};
