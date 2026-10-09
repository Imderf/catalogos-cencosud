<?php

use App\Actions\Review\UploadVersion;
use App\Domain\Projects\CatalogCards;
use App\Enums\Role;
use App\Events\Projects\ActivityLogged;
use App\Events\Projects\BoardChanged;
use App\Models\AdjustmentIntake;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sector;
use App\Models\Subtask;
use App\Notifications\Projects\ProjectAlert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(function () {
    Event::fake([BoardChanged::class, ActivityLogged::class]);
    Notification::fake();
    openHours();
    $this->daniela = userWithRole(Role::Agencia);
    $this->mate = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->project->members()->attach($this->daniela, ['role' => 'manager']);
    $this->project->members()->attach($this->mate, ['role' => 'member']);
    foreach ([['Backlog', 'backlog'], ['En revisión', 'review'], ['Aprobados', 'approved']] as $i => [$name, $kind]) {
        $this->project->lists()->create(['name' => $name, 'kind' => $kind, 'position' => ($i + 1) * 1024]);
    }
    $this->catalog = Catalog::factory()->create(['name' => 'Jumbo Octavilla']);
    $this->pages = collect([1, 2])->map(fn ($n) => Page::factory()->preparing()->create(['catalog_id' => $this->catalog->id, 'number' => $n]));
    $this->pages->each(fn ($p) => $p->sectors()->sync([Sector::where('slug', 'liquidos')->first()->id]));
});

test('a linked catalog lands in the Backlog with one automatic subtask per page that follows the review', function () {
    $this->actingAs($this->daniela)->put(route('catalogs.update', $this->catalog), ['name' => 'Jumbo Octavilla', 'brand_id' => $this->catalog->brand_id, 'kind' => 'octavilla', 'project_id' => $this->project->id]);
    $card = Card::where('catalog_id', $this->catalog->id)->firstOrFail();
    expect($card->list->kind)->toBe('backlog')->and($card->subtasks()->count())->toBe(2);
    $sub = $card->subtasks()->where('page_id', $this->pages[0]->id)->first();
    expect($sub->title)->toBe('Página 01 · Líquidos')->and($sub->status->value)->toBe('pending')->and($sub->isAutomatic())->toBeTrue();

    // automatic: nobody renames or moves its status by hand, nor deletes it
    $this->actingAs($this->daniela)->put(route('subtasks.update', $sub), ['status' => 'completed'])->assertSessionHas('error');
    $this->actingAs($this->daniela)->delete(route('subtasks.destroy', $sub))->assertSessionHas('error');
    $this->actingAs($this->daniela)->put(route('subtasks.update', $sub), ['assignees' => [$this->mate->id]])->assertSessionHasNoErrors(); // people yes

    // sending to Marketing: review → En proceso; approving the whole chain → Completado
    $this->actingAs($this->daniela)->post(route('catalogs.submit', $this->catalog));
    expect($sub->fresh()->status->value)->toBe('in_progress')->and($card->fresh()->list->kind)->toBe('review');
    closeWholeChain($this->pages[0]->fresh());
    expect($sub->fresh()->status->value)->toBe('completed')->and($sub->fresh()->completed_at)->not->toBeNull();

    // a page added later gets its subtask
    Page::factory()->preparing()->create(['catalog_id' => $this->catalog->id, 'number' => 3]);
    app(CatalogCards::class)->sync($this->catalog->fresh());
    expect($card->subtasks()->count())->toBe(3);
});

test('a client comment waits in the page subtask; the moderator approves it into a deliverable or dismisses it', function () {
    app(CatalogCards::class)->link($this->catalog, $this->project, $this->daniela);
    $this->actingAs($this->daniela)->post(route('catalogs.submit', $this->catalog));
    $marketing = userWithRole(Role::Marketing);
    $page = $this->pages[0]->fresh();

    $this->actingAs($marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, $page->number]), ['type' => 'precio', 'text' => 'El precio del arroz está mal', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2]])
        ->assertSessionHasNoErrors();
    $adjustment = $page->adjustments()->first();
    $intake = AdjustmentIntake::where('adjustment_id', $adjustment->id)->firstOrFail();
    $sub = $intake->subtask;
    expect($intake->isPending())->toBeTrue()->and($sub->page_id)->toBe($page->id)->and($sub->fresh()->status->value)->toBe('adjustments');
    Notification::assertSentTo($this->daniela, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'ingreso' && str_contains($n->payload['url'], 'subtarea='.$sub->id));
    Notification::assertNotSentTo($this->mate, ProjectAlert::class); // members are not moderators

    // the dialog shows the pending comment and the review shows the board link to Ogilvy only
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $sub->card_id, 'subtarea' => $sub->id]))
        ->assertInertia(fn ($p) => $p->where('subtask.intake_pending', 1)->where('subtask.intakes.0.status', 'pending')->where('subtask.intakes.0.adjustment.text', 'El precio del arroz está mal')->where('subtask.page.number', 1));
    $this->actingAs($this->daniela)->get(route('pages.show', [$this->catalog->slug, $page->number]))->assertInertia(fn ($p) => $p->where('page.adjustments.0.board.status', 'pending'));
    $this->actingAs($marketing)->get(route('pages.show', [$this->catalog->slug, $page->number]))->assertInertia(fn ($p) => $p->where('page.adjustments.0.board', null));

    // a member cannot decide; the moderator approves → the client's words land in the subtask thread, addressed to its people
    $sub->assignees()->attach($this->mate);
    $this->actingAs($this->mate)->post(route('intakes.approve', $intake))->assertSessionHas('error');
    $this->actingAs($this->daniela)->post(route('intakes.approve', $intake))->assertSessionHas('success');
    $comment = $sub->comments()->firstOrFail();
    expect($intake->fresh()->status)->toBe('approved')
        ->and($comment->user_id)->toBe($marketing->id)
        ->and($comment->adjustment_id)->toBe($adjustment->id)
        ->and($comment->body)->toContain('@['.$this->mate->name.']('.$this->mate->id.')')->toContain('arroz')
        ->and($sub->deliverables()->count())->toBe(0);
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'mencion' && str_contains($n->payload['actor'], $marketing->name));
    $this->actingAs($this->daniela)->post(route('intakes.approve', $intake))->assertSessionHas('error'); // once
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $sub->card_id, 'subtarea' => $sub->id]))
        ->assertInertia(fn ($p) => $p->where('subtask.comments.0.client.page_label', 'Página 01')->where('subtask.comments.0.client.open', true));

    // Ogilvy resolves the comment with a new version: the page subtask goes back to "En proceso" and the thread marks it resolved
    Storage::fake('local');
    app(UploadVersion::class)->handle($this->daniela, $page->fresh(), UploadedFile::fake()->image('p1v2.jpg', 800, 1000), [$adjustment->id]);
    expect($sub->fresh()->status->value)->toBe('in_progress');
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $sub->card_id, 'subtarea' => $sub->id]))
        ->assertInertia(fn ($p) => $p->where('subtask.comments.0.client.open', false));

    // a second comment dismissed with a reason
    $this->actingAs($marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, $page->number]), ['type' => 'descripcion', 'text' => 'Cambiar tilde', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.05, 'h' => 0.05]]);
    $second = AdjustmentIntake::where('adjustment_id', $page->adjustments()->latest('id')->value('id'))->firstOrFail();
    $this->actingAs($this->daniela)->post(route('intakes.dismiss', $second), ['reason' => 'Ya está en la versión 2'])->assertSessionHas('success');
    expect($second->fresh()->status)->toBe('dismissed')->and($second->fresh()->reason)->toBe('Ya está en la versión 2');
});

test('manual deliverables and list kinds', function () {
    $list = $this->project->lists()->first();
    $card = Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $this->project->id, 'created_by' => $this->daniela->id]);
    $sub = Subtask::factory()->create(['card_id' => $card->id, 'project_id' => $this->project->id]);
    $this->actingAs($this->mate)->post(route('subtasks.deliverables.store', $sub), ['title' => 'Cambiar logo'])->assertSessionHasNoErrors();
    expect($sub->fresh()->status->value)->toBe('adjustments')->and($sub->deliverables()->count())->toBe(1);
    $d = $sub->deliverables()->first();
    $this->actingAs($this->mate)->put(route('deliverables.update', $d), ['status' => 'done']);
    expect($d->fresh()->done_at)->not->toBeNull();
    $this->actingAs($this->mate)->delete(route('deliverables.destroy', $d))->assertSessionHas('error');
    $this->actingAs($this->daniela)->delete(route('deliverables.destroy', $d))->assertSessionHasNoErrors();

    // moving the "backlog" tag to another list clears it from the old one
    $other = $this->project->lists()->where('kind', 'review')->first();
    $this->actingAs($this->daniela)->put(route('lists.update', $other), ['kind' => 'backlog'])->assertSessionHasNoErrors();
    expect($other->fresh()->kind)->toBe('backlog')->and($list->fresh()->kind)->toBeNull();
    $this->actingAs($this->mate)->put(route('lists.update', $other), ['kind' => null])->assertSessionHas('error');
});
