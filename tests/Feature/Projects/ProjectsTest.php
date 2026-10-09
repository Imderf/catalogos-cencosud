<?php

use App\Enums\Role;
use App\Events\Projects\BoardChanged;
use App\Models\BoardList;
use App\Models\Brand;
use App\Models\Card;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(fn () => Event::fake([BoardChanged::class]));

function ogilvy(): User
{
    return userWithRole(Role::Agencia);
}

test('boards are invisible to the client', function () {
    $marketing = userWithRole(Role::Marketing);
    $this->actingAs($marketing)->get(route('projects.index'))->assertForbidden();
    $project = Project::factory()->create();
    $this->actingAs($marketing)->get(route('projects.show', $project))->assertForbidden();
    $this->actingAs($marketing)->get(route('catalogs.index'))->assertInertia(fn ($p) => $p->where('auth.user.can.projects', false));
});

test('Ogilvy creates a project and becomes its manager; others only see projects they belong to', function () {
    $daniela = ogilvy();
    $cencosud = Client::where('name', 'Cencosud')->first();

    $this->actingAs($daniela)->post(route('projects.store'), ['name' => 'Octavillas Q4', 'client_id' => $cencosud->id, 'color' => '#EF3B3A', 'description' => 'Todo lo de Q4'])
        ->assertRedirect();
    $project = Project::where('name', 'Octavillas Q4')->firstOrFail();
    expect($project->owner_id)->toBe($daniela->id)
        ->and($project->roleOf($daniela)?->value)->toBe('manager')
        ->and($project->lists()->count())->toBe(4); // Backlog, Por hacer, En curso, Hecho

    $other = ogilvy();
    $this->actingAs($other)->get(route('projects.index'))->assertInertia(fn ($p) => $p->has('projects', 0));
    $this->actingAs($other)->get(route('projects.show', $project))->assertForbidden();
    $this->actingAs(userWithRole(Role::Admin))->get(route('projects.show', $project))->assertOk();
});

test('managers add members with a role, and members cannot shape the project', function () {
    $daniela = ogilvy();
    $project = Project::factory()->create(['owner_id' => $daniela->id]);
    $mate = ogilvy();

    $this->actingAs($daniela)->post(route('projects.members.store', $project), ['user_id' => $mate->id, 'role' => 'member'])->assertSessionHas('success');
    expect($project->fresh()->roleOf($mate)?->value)->toBe('member');
    $this->actingAs($mate)->get(route('projects.show', $project))->assertOk();

    $this->actingAs($mate)->put(route('projects.update', $project), ['name' => 'Otro', 'color' => '#000000'])->assertSessionHas('error');
    $this->actingAs($mate)->post(route('projects.labels.store', $project), ['name' => 'Urgente', 'color' => '#ff0000'])->assertSessionHas('error');

    $this->actingAs($daniela)->put(route('projects.members.update', [$project, $mate]), ['role' => 'manager'])->assertSessionHas('success');
    expect($project->fresh()->roleOf($mate)?->value)->toBe('manager');
    $this->actingAs($daniela)->delete(route('projects.members.destroy', [$project, $mate]))->assertSessionHas('success');
    expect($project->fresh()->roleOf($mate))->toBeNull();
    $this->actingAs($daniela)->delete(route('projects.members.destroy', [$project, $daniela]))->assertSessionHas('error'); // owner stays
});

test('lists and cards are created, renamed, moved between lists and reordered by position', function () {
    $daniela = ogilvy();
    $project = Project::factory()->create(['owner_id' => $daniela->id]);
    $this->actingAs($daniela)->post(route('projects.lists.store', $project), ['name' => 'Por hacer'])->assertSessionHas('success');
    $this->actingAs($daniela)->post(route('projects.lists.store', $project), ['name' => 'Hecho']);
    [$todo, $done] = $project->lists()->orderBy('position')->get();
    expect($done->position)->toBeGreaterThan($todo->position);

    $this->actingAs($daniela)->post(route('projects.cards.store', $project), ['board_list_id' => $todo->id, 'title' => 'Diseñar portada'])->assertSessionHas('success');
    $this->actingAs($daniela)->post(route('projects.cards.store', $project), ['board_list_id' => $todo->id, 'title' => 'Revisar precios']);
    [$a, $b] = $todo->cards()->orderBy('position')->get();
    expect($b->position)->toBeGreaterThan($a->position);

    // move b above a inside the same list, then into "Hecho"
    $this->actingAs($daniela)->put(route('cards.move', $b), ['board_list_id' => $todo->id, 'position' => $a->position / 2])->assertSessionHasNoErrors();
    expect($todo->cards()->orderBy('position')->first()->id)->toBe($b->id);
    $this->actingAs($daniela)->put(route('cards.move', $b), ['board_list_id' => $done->id, 'position' => 1024]);
    expect($b->fresh()->board_list_id)->toBe($done->id);

    $this->actingAs($daniela)->put(route('lists.update', $todo), ['name' => 'Backlog'])->assertSessionHas('success');
    expect($todo->fresh()->name)->toBe('Backlog');
    $this->actingAs($daniela)->delete(route('lists.destroy', $todo))->assertSessionHas('error'); // not empty
    $this->actingAs($daniela)->delete(route('cards.destroy', $a))->assertSessionHas('success');
    $this->actingAs($daniela)->delete(route('lists.destroy', $todo))->assertSessionHas('success');
    Event::assertDispatched(BoardChanged::class);
});

test('a card is edited with priority, due date, assignees and labels; members delete only their own cards', function () {
    $daniela = ogilvy();
    $mate = ogilvy();
    $project = Project::factory()->create(['owner_id' => $daniela->id]);
    $project->members()->attach($mate, ['role' => 'member']);
    $list = BoardList::factory()->create(['project_id' => $project->id]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $project->id, 'created_by' => $daniela->id]);
    $this->actingAs($daniela)->post(route('projects.labels.store', $project), ['name' => 'Jumbo', 'color' => '#00aa00']);
    $label = $project->labels()->first();

    $this->actingAs($mate)->put(route('cards.update', $card), [
        'title' => 'Portada v2', 'description' => 'Con el nuevo logo', 'priority' => 'urgent', 'due_date' => '2026-10-01',
        'assignees' => [$mate->id], 'labels' => [$label->id],
    ])->assertSessionHasNoErrors();
    $card->refresh();
    expect($card->title)->toBe('Portada v2')->and($card->priority->value)->toBe('urgent')
        ->and($card->due_date->format('Y-m-d'))->toBe('2026-10-01')
        ->and($card->assignees->pluck('id')->all())->toBe([$mate->id])
        ->and($card->labels->pluck('id')->all())->toBe([$label->id]);

    // an outsider cannot be assigned
    $this->actingAs($mate)->put(route('cards.update', $card), ['title' => 'x', 'assignees' => [userWithRole(Role::Marketing)->id]])->assertSessionHasErrors('assignees.0');

    $this->actingAs($mate)->delete(route('cards.destroy', $card))->assertSessionHas('error'); // created by daniela
    $this->actingAs($mate)->put(route('cards.complete', $card))->assertSessionHasNoErrors();
    expect($card->fresh()->completed_at)->not->toBeNull();
    $this->actingAs($daniela)->delete(route('cards.destroy', $card))->assertSessionHas('success');
});

test('archived projects leave the list and refuse changes; the owner can delete', function () {
    $daniela = ogilvy();
    $project = Project::factory()->create(['owner_id' => $daniela->id]);
    $this->actingAs($daniela)->post(route('projects.archive', $project))->assertSessionHas('success');
    $this->actingAs($daniela)->get(route('projects.index'))->assertInertia(fn ($p) => $p->has('projects', 0));
    $this->actingAs($daniela)->post(route('projects.lists.store', $project), ['name' => 'x'])->assertSessionHas('error');
    $this->actingAs($daniela)->post(route('projects.unarchive', $project))->assertSessionHas('success');
    $this->actingAs($daniela)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));
    expect(Project::find($project->id))->toBeNull();
});

test('admins manage clients; brands hang from Cencosud', function () {
    $admin = userWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('admin.clients.store'), ['name' => 'Unilever', 'color' => '#1f5fb8'])->assertSessionHas('success');
    $client = Client::where('name', 'Unilever')->first();
    $this->actingAs($admin)->put(route('admin.clients.update', $client), ['name' => 'Unilever Andina', 'color' => '#1f5fb8', 'is_active' => false])->assertSessionHas('success');
    expect($client->fresh()->name)->toBe('Unilever Andina')->and($client->fresh()->is_active)->toBeFalse();
    $this->actingAs(ogilvy())->post(route('admin.clients.store'), ['name' => 'X', 'color' => '#000000'])->assertForbidden();
    expect(Brand::where('slug', 'jumbo')->first()->client->name)->toBe('Cencosud');
});

test('a new project can copy another board\'s setup: lists, labels, fields and people, never its cards', function () {
    $daniela = ogilvy();
    $mate = ogilvy();
    $source = Project::factory()->create(['owner_id' => $daniela->id, 'name' => 'Plantilla', 'client_id' => Client::where('name', 'Cencosud')->value('id')]);
    $source->members()->attach($mate, ['role' => 'member']);
    $source->lists()->create(['name' => 'Backlog', 'kind' => 'backlog', 'position' => 1024]);
    $source->lists()->create(['name' => 'Diseño', 'position' => 2048]);
    $source->labels()->create(['name' => 'Jumbo', 'color' => '#00aa00']);
    $source->fields()->create(['name' => 'Formato', 'type' => 'select', 'options' => ['A4', 'A5'], 'required' => true, 'position' => 1024]);
    $source->cards()->create(['board_list_id' => $source->lists()->first()->id, 'title' => 'No copiar', 'position' => 1024]);

    $this->actingAs($daniela)->post(route('projects.store'), ['name' => 'Octavillas 2027', 'color' => '#EF3B3A', 'template_id' => $source->id])->assertRedirect();
    $new = Project::where('name', 'Octavillas 2027')->firstOrFail();
    expect($new->client_id)->toBe($source->client_id)
        ->and($new->lists()->orderBy('position')->pluck('name')->all())->toBe(['Backlog', 'Diseño'])
        ->and($new->lists()->where('kind', 'backlog')->exists())->toBeTrue()
        ->and($new->labels()->pluck('name')->all())->toBe(['Jumbo'])
        ->and($new->fields()->first()->options)->toBe(['A4', 'A5'])->and($new->fields()->first()->required)->toBeTrue()
        ->and($new->roleOf($mate)?->value)->toBe('member')->and($new->roleOf($daniela)?->value)->toBe('manager')
        ->and($new->cards()->count())->toBe(0);

    // a board one cannot see is not a valid source
    $foreign = Project::factory()->create();
    $this->actingAs($daniela)->post(route('projects.store'), ['name' => 'Otro', 'color' => '#EF3B3A', 'template_id' => $foreign->id]);
    expect(Project::where('name', 'Otro')->first()->lists()->count())->toBe(4); // default lists instead
    $this->actingAs($daniela)->get(route('projects.index'))->assertInertia(fn ($p) => $p->has('templates'));
});
