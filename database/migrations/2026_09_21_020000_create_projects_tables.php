<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 9)->default('#EF3B3A');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('brands', fn (Blueprint $t) => $t->foreignId('client_id')->nullable()->after('id')->constrained()->nullOnDelete());

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 9)->default('#EF3B3A');
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /* manager: edits the project, members, labels, lists; member: works the cards */
            $table->string('role', 10)->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });
        Schema::create('board_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->double('position')->default(0);
            /* Optional meaning for catalog sync: preparation | review | approved */
            $table->string('kind', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_list_id')->constrained('board_lists')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->double('position')->default(0);
            $table->string('priority', 10)->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'board_list_id']);
        });
        Schema::create('card_user', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'user_id']);
        });
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 9)->default('#6B6B76');
            $table->timestamps();
        });
        Schema::create('card_label', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'label_id']);
        });
    }

    public function down(): void
    {
        foreach (['card_label', 'labels', 'card_user', 'cards', 'board_lists', 'project_members', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('brands', fn (Blueprint $t) => $t->dropConstrainedForeignId('client_id'));
        Schema::dropIfExists('clients');
    }
};
