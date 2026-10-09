<?php

use App\Domain\Projects\CatalogCards;
use App\Enums\Role;
use App\Events\Projects\BoardChanged;
use App\Models\Brand;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(function () {
    Event::fake([BoardChanged::class]);
    openHours();
    $this->daniela = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    foreach ([['Por hacer', 'preparation'], ['En revisión', 'review'], ['Aprobados', 'approved']] as $i => [$name, $kind]) {
        $this->project->lists()->create(['name' => $name, 'kind' => $kind, 'position' => ($i + 1) * 1024]);
    }
});

test('a catalog created on a project appears as a card in preparation and follows the review to the end', function () {
    Storage::fake('local');
    $food = Sector::where('slug', 'liquidos')->first();
    $this->actingAs($this->daniela)->post(route('catalogs.store'), [
        'name' => 'Jumbo Octavilla 3-5 oct', 'brand_id' => Brand::first()->id, 'kind' => 'octavilla', 'project_id' => $this->project->id,
        'pages' => [['image' => UploadedFile::fake()->image('p1.jpg', 800, 1000), 'sectors' => [$food->id]]],
    ])->assertRedirect();
    $catalog = Catalog::where('name', 'Jumbo Octavilla 3-5 oct')->firstOrFail();
    $card = Card::where('catalog_id', $catalog->id)->firstOrFail();
    expect($card->list->kind)->toBe('preparation')->and($card->title)->toBe($catalog->name);

    // the board shows the catalog's progress on the card, and the catalog shows its board
    $this->actingAs($this->daniela)->get(route('projects.show', $this->project))
        ->assertInertia(fn ($p) => $p->where('project.lists.0.cards.0.catalog.status_label', 'En preparación'));
    $this->actingAs($this->daniela)->get(route('catalogs.show', $catalog))
        ->assertInertia(fn ($p) => $p->where('catalog.project.id', $this->project->id));
    $this->actingAs(userWithRole(Role::Marketing))->get(route('catalogs.show', $catalog))
        ->assertInertia(fn ($p) => $p->where('catalog.project', null)); // the client never sees boards

    // sending to Marketing moves it to review
    $this->actingAs($this->daniela)->post(route('catalogs.submit', $catalog));
    expect($card->fresh()->list->kind)->toBe('review');

    // closing the whole chain completes it in "Aprobados"
    $page = $catalog->pages()->first();
    closeWholeChain($page->fresh());
    $card->refresh();
    expect($card->list->kind)->toBe('approved')->and($card->completed_at)->not->toBeNull()
        ->and($card->activities()->where('action', 'moved')->count())->toBe(2);

    // a post-production defect reopens it back to review
    $this->actingAs(userWithRole(Role::Marketing))->post(route('pages.defect', [$catalog->slug, $page->number]), ['type' => 'imagen', 'reason' => 'Se fue un precio'])->assertSessionHasNoErrors();
    $card->refresh();
    expect($card->list->kind)->toBe('review')->and($card->completed_at)->toBeNull();
});

test('a catalog can be linked or unlinked from Administrar; unlinking removes the card', function () {
    $catalog = Catalog::factory()->create(['name' => 'Metro Volante']);
    Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    $this->actingAs($this->daniela)->put(route('catalogs.update', $catalog), ['name' => 'Metro Volante', 'brand_id' => $catalog->brand_id, 'kind' => 'volante', 'project_id' => $this->project->id])
        ->assertSessionHas('success');
    expect(Card::where('catalog_id', $catalog->id)->first()?->list->kind)->toBe('review'); // pages already submitted
    $this->actingAs($this->daniela)->put(route('catalogs.update', $catalog), ['name' => 'Metro Volante', 'brand_id' => $catalog->brand_id, 'kind' => 'volante', 'project_id' => null]);
    expect(Card::where('catalog_id', $catalog->id)->exists())->toBeFalse();

    // an outsider's project cannot be picked
    $other = Project::factory()->create();
    $this->actingAs($this->daniela)->put(route('catalogs.update', $catalog), ['name' => 'Metro Volante', 'brand_id' => $catalog->brand_id, 'kind' => 'volante', 'project_id' => $other->id]);
    expect(Card::where('catalog_id', $catalog->id)->exists())->toBeFalse();
});

test('the stage helper reads preparation, review and approved', function () {
    $catalog = Catalog::factory()->create();
    $cards = app(CatalogCards::class);
    expect($cards->stage($catalog))->toBe('preparation');
    $page = Page::factory()->preparing()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    expect($cards->stage($catalog->fresh()))->toBe('preparation');
    $page->update(['submitted_at' => now(), 'waiting_since' => now()]);
    expect($cards->stage($catalog->fresh()))->toBe('review');
    closeWholeChain($page->fresh());
    expect($cards->stage($catalog->fresh()))->toBe('approved');
});

test('a catalog can adopt an existing task: its hand-written subtasks become the pages, in order, and unlinking keeps the task', function () {
    $daniela = $this->daniela;
    $catalog = Catalog::factory()->create(['name' => 'Volante Halloween']);
    foreach ([1, 2, 3] as $n) {
        Page::factory()->preparing()->create(['catalog_id' => $catalog->id, 'number' => $n]);
    }
    $list = $this->project->lists()->first();
    $task = Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $this->project->id, 'title' => 'Halloween Metro', 'created_by' => $daniela->id]);
    foreach (['Portada', 'Interior'] as $i => $t) {
        $task->subtasks()->create(['project_id' => $this->project->id, 'title' => $t, 'position' => ($i + 1) * 1024]);
    }

    $this->actingAs($daniela)->put(route('catalogs.update', $catalog), ['name' => 'Volante Halloween', 'brand_id' => $catalog->brand_id, 'kind' => 'volante', 'project_id' => $this->project->id, 'card_id' => $task->id])
        ->assertSessionHas('success');
    $task->refresh();
    expect($task->catalog_id)->toBe($catalog->id)->and($task->catalog_auto)->toBeFalse()->and($task->title)->toBe('Halloween Metro');
    $subs = $task->subtasks()->orderBy('position')->get();
    expect($subs)->toHaveCount(3)
        ->and($subs[0]->title)->toBe('Portada')->and($subs[0]->page->number)->toBe(1)
        ->and($subs[1]->title)->toBe('Interior')->and($subs[1]->page->number)->toBe(2)
        ->and($subs[2]->title)->toStartWith('Página 03');

    // the form offers the task while it is linked and hides tasks that belong to other catalogs
    $this->actingAs($daniela)->get(route('catalogs.edit', $catalog))->assertInertia(fn ($p) => $p->where('catalog.card_id', $task->id)->where('projects.0.cards.0.id', $task->id));
    $this->actingAs($daniela)->get(route('catalogs.create'))->assertInertia(fn ($p) => $p->where('projects.0.cards', []));

    // unlinking keeps the task and its own subtasks, drops the page ones
    $this->actingAs($daniela)->put(route('catalogs.update', $catalog), ['name' => 'Volante Halloween', 'brand_id' => $catalog->brand_id, 'kind' => 'volante', 'project_id' => null]);
    $task->refresh();
    expect($task->catalog_id)->toBeNull()->and(Card::find($task->id))->not->toBeNull()->and($task->subtasks()->count())->toBe(2)->and($task->subtasks()->whereNotNull('page_id')->count())->toBe(0);
});
