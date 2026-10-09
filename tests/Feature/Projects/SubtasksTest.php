<?php

use App\Enums\Role;
use App\Events\Projects\ActivityLogged;
use App\Events\Projects\BoardChanged;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Project;
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
    Storage::fake('local');
    $this->daniela = userWithRole(Role::Agencia);
    $this->mate = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->project->members()->attach($this->daniela, ['role' => 'manager']);
    $this->project->members()->attach($this->mate, ['role' => 'member']);
    $this->list = BoardList::factory()->create(['project_id' => $this->project->id]);
    $this->card = Card::factory()->create(['board_list_id' => $this->list->id, 'project_id' => $this->project->id, 'created_by' => $this->daniela->id, 'title' => 'Portada']);
});

test('members add subtasks, order them, and the card shows its progress', function () {
    $this->actingAs($this->mate)->post(route('cards.subtasks.store', $this->card), ['title' => 'FAQ AKI'])->assertSessionHasNoErrors();
    $this->actingAs($this->mate)->post(route('cards.subtasks.store', $this->card), ['title' => 'FAQ Maxi']);
    [$a, $b] = $this->card->subtasks()->get();
    expect($b->position)->toBeGreaterThan($a->position)->and($a->status->value)->toBe('pending');

    $this->actingAs($this->mate)->put(route('subtasks.move', $b), ['position' => $a->position / 2]);
    expect($this->card->subtasks()->first()->id)->toBe($b->id);

    $this->actingAs($this->mate)->put(route('subtasks.update', $a), ['status' => 'completed'])->assertSessionHasNoErrors();
    expect($a->fresh()->completed_at)->not->toBeNull();
    $this->actingAs($this->daniela)->get(route('projects.show', $this->project))
        ->assertInertia(fn ($p) => $p->where('project.lists.0.cards.0.subtasks_total', 2)->where('project.lists.0.cards.0.subtasks_open', 1));
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id]))
        ->assertInertia(fn ($p) => $p->has('card.subtasks', 2)->where('card.subtasks.0.title', 'FAQ Maxi')->has('subtask_statuses', 4));
});

test('assigning and moving the status notifies the people on the subtask; "mine" follows subtasks too', function () {
    $s = Subtask::factory()->create(['card_id' => $this->card->id, 'project_id' => $this->project->id, 'title' => 'FAQ B-sí']);
    $this->actingAs($this->daniela)->put(route('subtasks.update', $s), ['assignees' => [$this->mate->id], 'due_date' => '2026-10-02', 'priority' => 'high'])->assertSessionHasNoErrors();
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'asignacion' && str_contains($n->payload['url'], 'subtarea='.$s->id));

    $this->actingAs($this->daniela)->put(route('subtasks.update', $s), ['status' => 'adjustments']);
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'estado' && str_contains($n->payload['text'], 'Ajustes'));
    expect($s->fresh()->activities()->pluck('action')->all())->toContain('subtask_assigned', 'subtask_due', 'subtask_status');

    // mate is not on the card, but on its subtask: the card counts as his on the board and the panel
    $this->actingAs($this->mate)->get(route('projects.show', $this->project))->assertInertia(fn ($p) => $p->where('project.lists.0.cards.0.mine', true));
    $this->actingAs($this->mate)->get(route('panel'))->assertInertia(fn ($p) => $p->where('stats.mine', 1)->where('mine.0.title', 'FAQ B-sí')->where('mine.0.parent', 'Portada'));

    // an outsider cannot be assigned
    $this->actingAs($this->daniela)->put(route('subtasks.update', $s), ['assignees' => [userWithRole(Role::Marketing)->id]])->assertSessionHasErrors('assignees.0');
});

test('subtasks have their own conversation with files, apart from the card', function () {
    $s = Subtask::factory()->create(['card_id' => $this->card->id, 'project_id' => $this->project->id, 'title' => 'FAQ Titán']);
    $s->assignees()->attach($this->mate);
    $this->actingAs($this->daniela)->post(route('subtasks.comments.store', $s), ['body' => 'Va @[Compi]('.$this->mate->id.')', 'files' => [UploadedFile::fake()->image('ref.png')]])->assertSessionHasNoErrors();
    expect($s->comments()->count())->toBe(1)->and($this->card->comments()->count())->toBe(0)->and($this->card->attachments()->count())->toBe(0);
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'mencion');

    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id, 'subtarea' => $s->id]))
        ->assertInertia(fn ($p) => $p->where('subtask.title', 'FAQ Titán')->has('subtask.comments', 1)->where('subtask.comments.0.attachments.0.image', true)->has('subtask.activity'));
});

test('subtasks with a date show in the calendar; only the creator or a manager deletes', function () {
    $s = Subtask::factory()->create(['card_id' => $this->card->id, 'project_id' => $this->project->id, 'title' => 'FAQ Moblart', 'due_date' => now()->addDay(), 'created_by' => $this->daniela->id]);
    $this->actingAs($this->daniela)->get(route('calendar', ['mes' => now()->format('Y-m')]))
        ->assertInertia(fn ($p) => $p->has('cards', 1)->where('cards.0.title', 'FAQ Moblart')->where('cards.0.parent', 'Portada'));

    $this->actingAs($this->mate)->delete(route('subtasks.destroy', $s))->assertSessionHas('error');
    $this->actingAs($this->daniela)->delete(route('subtasks.destroy', $s))->assertSessionHasNoErrors();
    expect(Subtask::find($s->id))->toBeNull();
});
