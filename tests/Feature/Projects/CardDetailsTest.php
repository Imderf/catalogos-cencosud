<?php

use App\Enums\Role;
use App\Events\Projects\BoardChanged;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardComment;
use App\Models\Project;
use App\Notifications\Projects\ProjectAlert;
use App\Notifications\Projects\ProjectAlertMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(function () {
    Event::fake([BoardChanged::class]);
    Notification::fake();
    $this->daniela = userWithRole(Role::Agencia);
    $this->mate = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->project->members()->attach($this->daniela, ['role' => 'manager']);
    $this->project->members()->attach($this->mate, ['role' => 'member']);
    $list = BoardList::factory()->create(['project_id' => $this->project->id, 'name' => 'Por hacer']);
    $this->card = Card::factory()->create(['board_list_id' => $list->id, 'project_id' => $this->project->id, 'created_by' => $this->daniela->id, 'title' => 'Portada']);
});

test('a comment with a mention notifies the mentioned person and the card watchers, in app and by mail', function () {
    $body = 'Ojo @[Compañero]('.$this->mate->id.') con el logo';
    $this->actingAs($this->daniela)->post(route('cards.comments.store', $this->card), ['body' => $body])->assertSessionHasNoErrors();

    expect($this->card->comments()->count())->toBe(1);
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'mencion' && str_contains($n->payload['url'], 'tarjeta='.$this->card->id));
    Notification::assertSentTo($this->mate, ProjectAlertMail::class);
    Notification::assertNotSentTo($this->daniela, ProjectAlert::class); // the author never hears about their own comment

    // mate comments back: daniela (creator) hears about it
    $this->actingAs($this->mate)->post(route('cards.comments.store', $this->card), ['body' => 'Listo']);
    Notification::assertSentTo($this->daniela, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'comentario');

    // the bandeja links to the card
    $this->actingAs($this->mate)->get(route('notifications.index'))->assertOk();
});

test('assignment notifies the new assignee and the history records every change', function () {
    $this->actingAs($this->daniela)->put(route('cards.update', $this->card), ['title' => 'Portada v2', 'priority' => 'high', 'due_date' => '2026-10-01', 'assignees' => [$this->mate->id]])
        ->assertSessionHasNoErrors();
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'asignacion');

    $actions = $this->card->activities()->pluck('action')->all();
    expect($actions)->toContain('renamed', 'priority', 'due_date', 'assigned');

    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id]))
        ->assertInertia(fn ($p) => $p->where('card.title', 'Portada v2')->has('card.activity', 4)->where('card.activity.0.text', 'asignó a '.$this->mate->name));
});

test('files and links attach to a card, download through the session and can be removed by their author', function () {
    Storage::fake('local');
    $this->actingAs($this->mate)->post(route('cards.attachments.store', $this->card), ['files' => [UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf')]])
        ->assertSessionHas('success');
    $this->actingAs($this->mate)->post(route('cards.attachments.store', $this->card), ['url' => 'https://drive.google.com/x', 'name' => 'Carpeta Drive'])
        ->assertSessionHas('success');
    $file = $this->card->attachments()->where('kind', 'file')->first();
    Storage::disk('local')->assertExists($file->path);

    $this->actingAs($this->daniela)->get(route('attachments.show', $file))->assertOk();
    $this->actingAs(userWithRole(Role::Marketing))->get(route('attachments.show', $file))->assertForbidden();

    $this->actingAs($this->daniela)->delete(route('attachments.destroy', $file))->assertSessionHas('success'); // manager
    Storage::disk('local')->assertMissing($file->path);
    expect($this->card->attachments()->count())->toBe(1);
});

test('managers define custom fields; cards store typed values and duplicates carry them', function () {
    $this->actingAs($this->mate)->post(route('projects.fields.store', $this->project), ['name' => 'Formato', 'type' => 'select', 'options' => ['A4', 'A5']])->assertSessionHas('error');
    $this->actingAs($this->daniela)->post(route('projects.fields.store', $this->project), ['name' => 'Formato', 'type' => 'select', 'options' => ['A4', 'A5', '']])->assertSessionHas('success');
    $this->actingAs($this->daniela)->post(route('projects.fields.store', $this->project), ['name' => 'Páginas', 'type' => 'number'])->assertSessionHas('success');
    $this->actingAs($this->daniela)->post(route('projects.fields.store', $this->project), ['name' => 'Aprobado', 'type' => 'checkbox'])->assertSessionHas('success');
    [$formato, $paginas, $aprobado] = $this->project->fields()->orderBy('position')->get();
    expect($formato->options)->toBe(['A4', 'A5']);

    $this->actingAs($this->daniela)->put(route('projects.fields.reorder', $this->project), ['order' => [$aprobado->id, $formato->id, $paginas->id]]);
    expect($this->project->fields()->orderBy('position')->pluck('id')->all())->toBe([$aprobado->id, $formato->id, $paginas->id]);

    $this->actingAs($this->mate)->put(route('cards.fields', $this->card), ['values' => [$formato->id => 'A5', $paginas->id => '16', $aprobado->id => true]])->assertSessionHasNoErrors();
    $this->actingAs($this->mate)->put(route('cards.fields', $this->card), ['values' => [$formato->id => 'B3']]); // not an option → empty
    $values = $this->card->fieldValues()->get()->keyBy('custom_field_id');
    expect($values[$formato->id]->value)->toBeNull()->and($values[$paginas->id]->value)->toEqual(16)->and($values[$aprobado->id]->value)->toBeTrue();

    $this->actingAs($this->mate)->post(route('cards.duplicate', $this->card), ['title' => 'Portada (copia)', 'project_id' => $this->project->id, 'board_list_id' => $this->card->board_list_id, 'copy_assignees' => true, 'count' => 1])->assertSessionHas('success');
    $copy = Card::where('title', 'Portada (copia)')->firstOrFail();
    expect($copy->position)->toBeGreaterThan($this->card->position)
        ->and($copy->fieldValues()->count())->toBe(3)
        ->and($copy->activities()->first()->action)->toBe('duplicated');

    $this->actingAs($this->daniela)->delete(route('fields.destroy', $paginas))->assertSessionHas('success');
    expect($copy->fieldValues()->count())->toBe(2);
});

test('comments are edited by their author and removed by the author or a manager', function () {
    $this->actingAs($this->mate)->post(route('cards.comments.store', $this->card), ['body' => 'Primero']);
    $comment = $this->card->comments()->first();
    $this->actingAs($this->daniela)->put(route('comments.update', $comment), ['body' => 'x'])->assertSessionHas('error');
    $this->actingAs($this->mate)->put(route('comments.update', $comment), ['body' => 'Primero, corregido'])->assertSessionHasNoErrors();
    expect($comment->fresh()->body)->toBe('Primero, corregido');
    $outsider = userWithRole(Role::Agencia);
    $this->actingAs($outsider)->delete(route('comments.destroy', $comment))->assertSessionHas('error');
    $this->actingAs($this->daniela)->delete(route('comments.destroy', $comment))->assertSessionHasNoErrors();
    expect($this->card->comments()->count())->toBe(0);
});

test('a card has a start date that cannot come after its due date', function () {
    $this->actingAs($this->daniela)->put(route('cards.update', $this->card), ['title' => 'Portada', 'start_date' => '2026-10-10', 'due_date' => '2026-10-05'])->assertSessionHasErrors('due_date');
    $this->actingAs($this->daniela)->put(route('cards.update', $this->card), ['title' => 'Portada', 'start_date' => '2026-10-01', 'due_date' => '2026-10-05'])->assertSessionHasNoErrors();
    $this->card->refresh();
    expect($this->card->start_date->toDateString())->toBe('2026-10-01')->and($this->card->activities()->pluck('action')->all())->toContain('start_date');
    $this->actingAs($this->daniela)->get(route('projects.show', $this->project))->assertInertia(fn ($p) => $p->where('project.lists.0.cards.0.start_date', '2026-10-01'));
});

test('a comment can be answered (one level deep) and the answered author is told; client comments cannot be edited', function () {
    $this->actingAs($this->mate)->post(route('cards.comments.store', $this->card), ['body' => 'Primero']);
    $first = $this->card->comments()->first();
    $this->actingAs($this->daniela)->post(route('cards.comments.store', $this->card), ['body' => 'Respuesta', 'parent_id' => $first->id])->assertSessionHasNoErrors();
    $reply = $this->card->comments()->where('parent_id', $first->id)->first();
    expect($reply)->not->toBeNull()->and($reply->body)->toBe('Respuesta');
    Notification::assertSentTo($this->mate, ProjectAlert::class, fn ($n) => $n->payload['kind'] === 'respuesta' && str_contains($n->payload['text'], 'respondió a tu comentario'));

    // answering a reply hangs it under the original comment, never deeper
    $this->actingAs($this->mate)->post(route('cards.comments.store', $this->card), ['body' => 'Tercero', 'parent_id' => $reply->id])->assertSessionHasNoErrors();
    expect($this->card->comments()->where('parent_id', $first->id)->count())->toBe(2);
    $this->actingAs($this->mate)->post(route('cards.comments.store', $this->card), ['body' => 'x', 'parent_id' => 999999])->assertSessionHas('error');

    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id]))
        ->assertInertia(fn ($p) => $p->where('card.comments.0.parent_id', null)->where('card.comments.1.parent_id', $first->id)->where('card.comments.1.can_edit', true)->where('card.comments.0.can_edit', false));

    // deleting the original leaves the replies on their own
    $this->actingAs($this->mate)->delete(route('comments.destroy', $first))->assertSessionHasNoErrors();
    expect($reply->fresh()->parent_id)->toBeNull();

    // a comment that came from the client's review is nobody's to edit on the board
    $client = (new CardComment(['card_id' => $this->card->id, 'user_id' => $this->daniela->id, 'body' => 'x']))->forceFill(['adjustment_id' => 5]);
    expect($client->isEditableBy($this->daniela))->toBeFalse()->and($reply->fresh()->isEditableBy($this->daniela))->toBeTrue();
});
