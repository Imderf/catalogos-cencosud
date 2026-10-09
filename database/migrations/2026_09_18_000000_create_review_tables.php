<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value');
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('current_version')->default(1);
            /* Since when the page waits for the approval of the stage it is at (esperaDesde). */
            $table->timestamp('waiting_since')->nullable();
            $table->timestamps();
            $table->unique(['catalog_id', 'number']);
        });

        Schema::create('page_sector', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->primary(['page_id', 'sector_id']);
        });

        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('image_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['page_id', 'number']);
        });

        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin_role', 20);
            $table->string('type', 20);
            $table->text('text');
            $table->jsonb('box');
            $table->string('status', 10)->default('open');
            $table->unsignedSmallInteger('resolved_in_version')->nullable();
            $table->timestamp('resolved_at')->nullable();
            /* Remaining descending route, first element is the role holding the adjustment now. */
            $table->jsonb('route');
            $table->boolean('is_defect')->default(false);
            $table->timestamp('stage_since');
            $table->timestamp('received_by_agency_at')->nullable();
            $table->timestamps();
            $table->index(['page_id', 'status']);
        });

        Schema::create('adjustment_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('adjustment_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->timestamps();
            $table->unique(['page_id', 'version', 'role']);
        });

        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->string('type', 20);
            $table->text('reason');
            $table->foreignId('adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['defects', 'approvals', 'adjustment_replies', 'adjustment_validations', 'adjustments', 'page_versions', 'page_sector', 'pages', 'catalogs', 'brands', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
