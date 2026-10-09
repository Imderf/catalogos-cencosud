<?php

use App\Actions\Review\UploadVersion;
use App\Domain\Projects\CatalogCards;
use App\Domain\Review\Clocks;
use App\Enums\Role;
use App\Events\Projects\ActivityLogged;
use App\Events\Projects\BoardChanged;
use App\Models\Adjustment;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    Event::fake([BoardChanged::class, ActivityLogged::class]);
    Notification::fake();
    Storage::fake('local');
    openHours();
    $this->daniela = userWithRole(Role::Agencia);
    $this->marketing = userWithRole(Role::Marketing);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->project->lists()->create(['name' => 'Backlog', 'kind' => 'backlog', 'position' => 1024]);
});

function catalogWithPriority(Project $project, string $priority, string $name): Page
{
    $catalog = Catalog::factory()->create(['name' => $name]);
    $page = Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    $page->sectors()->sync([Sector::where('slug', 'liquidos')->first()->id]);
    $card = app(CatalogCards::class)->link($catalog, $project, test()->daniela);
    $card->update(['priority' => $priority]);

    return $page->fresh();
}

function clientComment(Page $page, string $text): Adjustment
{
    test()->travel(1)->minute(); // distinct created_at: the page lists adjustments in that order
    test()->actingAs(test()->marketing)->post(route('pages.adjustments.store', [$page->catalog->slug, $page->number]), ['type' => 'precio', 'text' => $text, 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]])->assertSessionHasNoErrors();

    return $page->adjustments()->reorder('id', 'desc')->first();
}

test('Ogilvy clocks start by task priority: the urgent one runs, the low one waits until it is resolved', function () {
    $urgentPage = catalogWithPriority($this->project, 'urgent', 'Urgente');
    $lowPage = catalogWithPriority($this->project, 'low', 'Tranquilo');
    $clocks = app(Clocks::class);

    $low = clientComment($lowPage, 'Cambiar precio');       // arrives first, but its task is low priority
    expect($low->fresh()->agency_started_at)->not->toBeNull(); // nothing higher pending yet: it starts
    $urgent = clientComment($urgentPage, 'Precio mal');
    expect($urgent->fresh()->agency_started_at)->not->toBeNull(); // top priority starts at once

    // a second low comment arrives while the urgent one is open: it waits
    $low2 = clientComment($lowPage, 'Otro precio');
    expect($low2->fresh()->agency_started_at)->toBeNull()
        ->and($clocks->isQueued($low2->fresh()))->toBeTrue()
        ->and($clocks->adjustmentRemaining($low2->fresh()))->toBeNull()
        ->and($clocks->adjustmentState($low2->fresh()))->toBe('espera')
        ->and($clocks->adjustmentWhere($low2->fresh()))->toBe('En cola · Ogilvy');
    $this->actingAs($this->daniela)->get(route('pages.show', [$lowPage->catalog->slug, 1]))
        ->assertInertia(fn ($p) => $p->where('page.adjustments.1.queued', true)->where('page.adjustments.1.task_priority', 'Baja')->where('page.adjustments.0.queued', false));

    // the page clock times only the running one and counts the queued one
    $clock = $clocks->pageClock($lowPage->fresh());
    expect($clock->where)->toBe('1 en Ogilvy · 1 en cola · 0 en el cliente')->and($clock->total)->toBe(30.0)->and($clock->openCount)->toBe(2);

    // the deadline command never alerts a queued clock
    $this->artisan('review:check-deadlines')->assertSuccessful();
    expect($low2->fresh()->alert_level)->toBeNull();

    // Ogilvy resolves the urgent one with a new version: the waiting one starts now, from now
    $this->travel(30)->minutes();
    app(UploadVersion::class)->handle($this->daniela, $urgentPage->fresh(), UploadedFile::fake()->image('v2.jpg', 800, 1000), [$urgent->id]);
    $started = $low2->fresh();
    expect($started->agency_started_at)->not->toBeNull()
        ->and($started->stage_since->gte(now()->subMinute()))->toBeTrue()
        ->and($clocks->adjustmentRemaining($started))->toBeGreaterThan(0);
});

test('same priority runs together, and raising a task\'s priority lets its comments start', function () {
    $a = catalogWithPriority($this->project, 'medium', 'A');
    $b = catalogWithPriority($this->project, 'medium', 'B');
    $one = clientComment($a, 'x');
    $two = clientComment($b, 'y');
    expect($one->fresh()->agency_started_at)->not->toBeNull()->and($two->fresh()->agency_started_at)->not->toBeNull();

    $high = catalogWithPriority($this->project, 'high', 'C');
    clientComment($high, 'z');
    $late = clientComment($a, 'w'); // medium behind a high: waits
    expect($late->fresh()->agency_started_at)->toBeNull();

    $card = Card::where('catalog_id', $a->catalog_id)->first();
    $this->actingAs($this->daniela)->put(route('cards.update', $card), ['title' => $card->title, 'priority' => 'urgent'])->assertSessionHasNoErrors();
    expect($late->fresh()->agency_started_at)->not->toBeNull();
});

test('catalogs without a task count as medium priority', function () {
    $catalog = Catalog::factory()->create();
    $page = Page::factory()->create(['catalog_id' => $catalog->id, 'number' => 1]);
    $page->sectors()->sync([Sector::where('slug', 'liquidos')->first()->id]);
    $high = catalogWithPriority($this->project, 'high', 'Alta');
    clientComment($high, 'primero');
    $free = clientComment($page->fresh(), 'sin tarea');
    expect($free->fresh()->agency_started_at)->toBeNull();
    $low = catalogWithPriority($this->project, 'low', 'Baja');
    $this->travel(1)->minute();
    app(UploadVersion::class)->handle($this->daniela, $high->fresh(), UploadedFile::fake()->image('v2.jpg', 800, 1000), $high->fresh()->adjustments->pluck('id')->all());
    expect($free->fresh()->agency_started_at)->not->toBeNull();
    expect(clientComment($low, 'ahora')->fresh()->agency_started_at)->toBeNull(); // medium (no task) still open above it
});
