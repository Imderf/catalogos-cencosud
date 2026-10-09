<?php

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Project;

require_once __DIR__.'/../Review/Helpers.php';

test('projects, the calendar and the catalog form know each project\'s client', function () {
    $daniela = userWithRole(Role::Agencia);
    $cencosud = Client::where('name', 'Cencosud')->first();
    $other = Client::factory()->create(['name' => 'Unilever']);
    $a = Project::factory()->create(['owner_id' => $daniela->id, 'name' => 'A', 'client_id' => $cencosud->id]);
    $b = Project::factory()->create(['owner_id' => $daniela->id, 'name' => 'B', 'client_id' => $other->id]);
    Project::factory()->create(['owner_id' => $daniela->id, 'name' => 'C']);

    $this->actingAs($daniela)->get(route('projects.index'))->assertInertia(fn ($p) => $p->has('projects', 3)->where('filters.cliente', null));
    $this->actingAs($daniela)->get(route('projects.index', ['cliente' => $other->id]))
        ->assertInertia(fn ($p) => $p->has('projects', 1)->where('projects.0.name', 'B')->where('projects.0.client.name', 'Unilever')->where('filters.cliente', $other->id));

    $this->actingAs($daniela)->get(route('calendar', ['cliente' => $cencosud->id]))
        ->assertInertia(fn ($p) => $p->has('projects', 1)->where('projects.0.id', $a->id)->has('clients')->where('filter.cliente', $cencosud->id));

    $this->actingAs($daniela)->get(route('catalogs.create'))
        ->assertInertia(fn ($p) => $p->where('brands.0.client_id', $cencosud->id)->has('projects', 3)->where('projects.0.client_id', fn ($v) => $v === null || is_int($v)));
    expect($b->fresh()->client_id)->toBe($other->id);
    expect(Brand::where('slug', 'jumbo')->value('client_id'))->toBe($cencosud->id);
});
