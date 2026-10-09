<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            /* Mentions travel inside the body as @[Nombre](userId). */
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('card_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); /* file | link */
            $table->string('name');
            $table->string('path')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('type', 15); /* text number date select multiselect checkbox url user */
            $table->json('options')->nullable();
            $table->double('position')->default(0);
            $table->timestamps();
        });
        Schema::create('card_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['card_id', 'custom_field_id']);
        });
        Schema::create('card_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->json('meta')->nullable();
            $table->timestamp('created_at');
            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['card_activities', 'card_field_values', 'custom_fields', 'card_attachments', 'card_comments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
