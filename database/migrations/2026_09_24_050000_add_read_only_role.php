<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** "Ogilvy · Solo lectura": sees catalogs, pages, reports and the calendar; uploads, approves or comments nothing. */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('lectura', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'lectura')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
