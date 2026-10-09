<?php

use App\Models\User;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())->get('/settings/profile')->assertOk();
});

test('users can update their name but not their email', function () {
    $user = User::factory()->create(['email' => 'valeria.ortiz@cencosud.example']);

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Valeria S.', 'email' => 'otro@correo.com', 'notification_mode' => 'instant'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->name)->toBe('Valeria S.')
        ->and($user->email)->toBe('valeria.ortiz@cencosud.example');
});

test('users cannot delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertMethodNotAllowed();
    expect($user->fresh())->not->toBeNull();
});
