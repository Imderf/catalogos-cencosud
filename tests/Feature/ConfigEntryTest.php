<?php

use App\Enums\Role;
use App\Models\User;

test('the configuration entry sends each admin to their first tab', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/configuracion')->assertRedirect('/tiempos');

    $marketing = User::factory()->role(Role::Marketing)->create();
    $this->actingAs($marketing)->get('/configuracion')->assertRedirect('/admin/usuarios');
    $this->actingAs($marketing)->get('/admin/usuarios')->assertForbidden();
});
