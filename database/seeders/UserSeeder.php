<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo users mirroring docs/prototipo/README.md. Password for all: "password".
 */
class UserSeeder extends Seeder
{
    public const USERS = [
        ['Admin Ogilvy', 'admin@ogilvy.example', 'Ogilvy Colombia', Role::Admin, null],
        ['Daniela Pardo', 'daniela.pardo@ogilvy.example', 'Ogilvy Colombia', Role::Agencia, null],
        ['Juliana Vélez', 'juliana.velez@ogilvy.example', 'Ogilvy Colombia', Role::Lectura, null],
        ['Valeria Ortiz', 'valeria.ortiz@cencosud.example', 'Cencosud · Marketing', Role::Marketing, null],
        ['Tomás Rincón', 'tomas.rincon@cencosud.example', 'Cencosud · Planificación', Role::Planificacion, null],
        ['Hernán Castro', 'hernan.castro@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['liquidos', 'rancho']],
        ['Lucía Mora', 'lucia.mora@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['liquidos']],
        ['Felipe Arango', 'felipe.arango@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['dph']],
        ['Mónica Lara', 'monica.lara@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['bazar', 'textil', 'electro']],
        ['Gabriela Soto', 'gabriela.soto@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['textil']],
        ['Esteban Duque', 'esteban.duque@cencosud.example', 'Cencosud · Comercial', Role::Comercial, ['electro']],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$name, $email, $org, $role, $sectors]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'organization' => $org,
                'password' => 'password',
                'is_active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role->value]);
            $user->sectors()->sync($sectors ? Sector::whereIn('slug', $sectors)->pluck('id') : []);
        }
    }
}
