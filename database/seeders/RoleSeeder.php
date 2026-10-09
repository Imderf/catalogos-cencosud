<?php

namespace Database\Seeders;

use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /** Permission name => roles that hold it. */
    public const PERMISSIONS = [
        'users.manage' => [Role::Admin],
        'catalogs.manage' => [Role::Admin, Role::Agencia],
        'sla.manage' => [Role::Admin, Role::Agencia],
        /* Boards, calendar and dashboard are Ogilvy-only: the client never sees them. */
        'projects.access' => [Role::Admin, Role::Agencia],
        'clients.manage' => [Role::Admin],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        foreach (self::PERMISSIONS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'web');
            $permission->syncRoles(array_map(fn (Role $r) => $r->value, $roles));
        }
    }
}
