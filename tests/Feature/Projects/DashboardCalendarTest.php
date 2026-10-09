<?php

use App\Domain\Projects\Activity;
use App\Enums\Role;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Client;
use App\Models\Project;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(function () {
    $this->daniela = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id, 'name' => 'Q4']);
    $this->list = BoardList::factory()->create(['project_id' => $this->project->id]);
    $this->mine = Card::factory()->create(['board_list_id' => $this->list->id, 'project_id' => $this->project->id, 'title' => 'Mía', 'due_date' => now()->subDay()]);
    $this->mine->assignees()->attach($this->daniela);
    app(Activity::class)->log($this->mine, $this->daniela, 'created');
    Card::factory()->create(['board_list_id' => $this->list->id, 'project_id' => $this->project->id, 'title' => 'Otra', 'due_date' => now()->addDays(2)]);
    Card::factory()->create(['board_list_id' => $this->list->id, 'project_id' => $this->project->id, 'title' => 'Hecha', 'completed_at' => now()]);
});

test('the panel sums up my cards, overdue work and project health for Ogilvy only', function () {
    $this->actingAs($this->daniela)->get(route('panel'))->assertInertia(fn ($p) => $p
        ->where('stats.projects', 1)->where('stats.open', 2)->where('stats.mine', 1)->where('stats.overdue', 1)
        ->where('mine.0.title', 'Mía')->where('mine.0.overdue', true)
        ->where('projects.0.total', 3)->where('projects.0.done', 1)
        ->has('catalogs')->has('activity', 1)->where('activity.0.card', 'Mía'));
    $this->actingAs(userWithRole(Role::Marketing))->get(route('panel'))->assertForbidden();
    $this->actingAs($this->daniela)->get(route('dashboard'))->assertRedirect(route('panel'));
    $this->actingAs(userWithRole(Role::Marketing))->get(route('dashboard'))->assertRedirect(route('catalogs.index'));
});

test('the calendar lists the cards due in the month and filters by project', function () {
    $mes = now()->format('Y-m');
    $this->actingAs($this->daniela)->get(route('calendar', ['mes' => $mes]))->assertInertia(fn ($p) => $p
        ->where('month', $mes)->has('cards', 2)->where('cards.0.title', 'Mía')->where('cards.0.mine', true));
    $other = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->actingAs($this->daniela)->get(route('calendar', ['mes' => $mes, 'proyecto' => $other->id]))->assertInertia(fn ($p) => $p->has('cards', 0));
    $this->actingAs($this->daniela)->get(route('calendar', ['mes' => '2030-01']))->assertInertia(fn ($p) => $p->has('cards', 0)->where('label', 'Enero 2030'));
});

test('the client sees a read-only calendar of the projects tied to a client, without people or board links', function () {
    $mes = now()->format('Y-m');
    $client = Client::factory()->create();
    $forClient = Project::factory()->create(['owner_id' => $this->daniela->id, 'name' => 'Cencosud Q4', 'client_id' => $client->id]);
    $list = BoardList::factory()->create(['project_id' => $forClient->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $forClient->id, 'title' => 'Visible', 'due_date' => now()->addDay()]);
    $catalog = Catalog::factory()->create();
    Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $forClient->id, 'title' => 'Catálogo', 'due_date' => now()->addDays(3), 'catalog_id' => $catalog->id]);

    // "Q4" has no client: internal, the client never sees it; the catalog card leads to the catalog, the other nowhere
    $this->actingAs(userWithRole(Role::Comercial))->get(route('calendar', ['mes' => $mes, 'persona' => $this->daniela->id]))->assertOk()->assertInertia(fn ($p) => $p
        ->where('read_only', true)->has('cards', 2)->has('people', 0)->has('clients', 0)->has('projects', 1)
        ->where('cards.0.title', 'Visible')->where('cards.0.url', null)
        ->where('cards.1.url', route('catalogs.show', $catalog->slug))
        ->where('filter.persona', null));
    $this->actingAs($this->daniela)->get(route('calendar', ['mes' => $mes]))->assertInertia(fn ($p) => $p->where('read_only', false));
});

test('the panel filters by project, person and date range', function () {
    $mate = userWithRole(Role::Agencia);
    $this->project->members()->attach($mate, ['role' => 'member']);
    $other = Project::factory()->create(['owner_id' => $this->daniela->id, 'name' => 'Otro']);
    $olist = BoardList::factory()->create(['project_id' => $other->id]);
    $far = Card::factory()->create(['board_list_id' => $olist->id, 'project_id' => $other->id, 'title' => 'Lejana', 'due_date' => '2027-03-01']);
    $far->assignees()->attach($mate);

    $this->actingAs($this->daniela)->get(route('panel', ['proyecto' => $other->id]))
        ->assertInertia(fn ($p) => $p->where('stats.projects', 1)->where('stats.open', 1)->where('filters.proyecto', $other->id)->has('options.people', 2)->has('options.projects', 2));
    $this->actingAs($this->daniela)->get(route('panel', ['persona' => $mate->id]))
        ->assertInertia(fn ($p) => $p->where('stats.open', 1)->where('stats.mine', 1)->where('mine.0.title', 'Lejana'));
    $this->actingAs($this->daniela)->get(route('panel', ['desde' => '2027-01-01', 'hasta' => '2027-12-31']))
        ->assertInertia(fn ($p) => $p->where('stats.open', 1)->where('due_soon.0.title', 'Lejana')->where('filters.desde', '2027-01-01'));
    $this->actingAs($this->daniela)->get(route('panel', ['desde' => 'no-es-fecha']))->assertInertia(fn ($p) => $p->where('filters.desde', null)->where('stats.open', 3));
});
