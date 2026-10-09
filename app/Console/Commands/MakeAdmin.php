<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\BrandSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** First deployment: seeds the catalog data and creates the administrator that will create everyone else. */
class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin {email} {name} {--password=}';

    protected $description = 'Crea (o promueve) el usuario administrador y siembra roles, sectores, marcas y tiempos';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => SectorSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => BrandSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);

        $password = $this->option('password') ?: Str::password(16, symbols: false);
        $user = User::updateOrCreate(['email' => Str::lower($this->argument('email'))], [
            'name' => $this->argument('name'),
            'organization' => 'Ogilvy Colombia',
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([Role::Admin->value]);

        $this->info("Administrador listo: {$user->email}");
        $this->line('Contraseña inicial (cámbiala en el primer ingreso): '.$password);

        return self::SUCCESS;
    }
}
