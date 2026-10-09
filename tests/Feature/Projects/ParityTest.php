<?php

use App\Enums\Role;
use App\Events\Projects\ActivityLogged;
use App\Events\Projects\BoardChanged;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\ResponseFactory;

require_once __DIR__.'/../Review/Helpers.php';

beforeEach(function () {
    Event::fake([BoardChanged::class, ActivityLogged::class]);
    Notification::fake();
    Storage::fake('local');
    $this->daniela = userWithRole(Role::Agencia);
    $this->project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $this->project->members()->attach($this->daniela, ['role' => 'manager']);
    $this->list = BoardList::factory()->create(['project_id' => $this->project->id, 'name' => 'Por hacer']);
    $this->card = Card::factory()->create(['board_list_id' => $this->list->id, 'project_id' => $this->project->id, 'created_by' => $this->daniela->id, 'title' => 'Portada', 'due_date' => '2026-10-01']);
});

test('duplicating with options makes numbered copies in another project, matching labels and fields by name', function () {
    $this->actingAs($this->daniela)->post(route('projects.labels.store', $this->project), ['name' => 'Jumbo', 'color' => '#00aa00']);
    $this->actingAs($this->daniela)->post(route('projects.fields.store', $this->project), ['name' => 'Formato', 'type' => 'text']);
    $label = $this->project->labels()->first();
    $field = $this->project->fields()->first();
    $this->card->labels()->sync([$label->id]);
    $this->card->fieldValues()->create(['custom_field_id' => $field->id, 'value' => 'A4']);
    $this->actingAs($this->daniela)->post(route('cards.attachments.store', $this->card), ['url' => 'https://x.test', 'name' => 'Link']);

    $other = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $other->members()->attach($this->daniela, ['role' => 'manager']);
    $otherList = BoardList::factory()->create(['project_id' => $other->id, 'name' => 'Backlog']);
    $other->fields()->create(['name' => 'Formato', 'type' => 'text', 'position' => 1]); // same name → copied; the label has no twin → skipped

    $this->actingAs($this->daniela)->post(route('cards.duplicate', $this->card), [
        'title' => 'Copia', 'project_id' => $other->id, 'board_list_id' => $otherList->id, 'copy_due_date' => true, 'copy_attachments' => true, 'count' => 2,
    ])->assertRedirect(route('projects.show', $other->id))->assertSessionHas('success', fn ($m) => str_contains($m, '2 copias') && str_contains($m, '1 etiqueta(s)'));
    $copies = $other->cards()->orderBy('title')->get();
    expect($copies->pluck('title')->all())->toBe(['Copia (1)', 'Copia (2)'])
        ->and($copies[0]->due_date->toDateString())->toBe('2026-10-01')
        ->and($copies[0]->fieldValues()->count())->toBe(1)
        ->and($copies[0]->attachments()->count())->toBe(1)
        ->and($copies[0]->labels()->count())->toBe(0);
});

test('a comment can carry files; the card keeps its own attachment list apart', function () {
    $this->actingAs($this->daniela)->post(route('cards.comments.store', $this->card), ['body' => '', 'files' => [UploadedFile::fake()->image('foto.png')]])->assertSessionHasNoErrors();
    $comment = $this->card->comments()->first();
    expect($comment->body)->toBe('(archivo adjunto)')->and($comment->attachments()->count())->toBe(1)->and($this->card->attachments()->count())->toBe(0);
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id]))
        ->assertInertia(fn ($p) => $p->where('card.comments.0.attachments.0.image', true)->where('card.attachments', []));
});

test('image fields upload through their own endpoint and clear by sending an empty value', function () {
    $this->actingAs($this->daniela)->post(route('projects.fields.store', $this->project), ['name' => 'Referencia', 'type' => 'image', 'required' => true]);
    $field = $this->project->fields()->first();
    expect($field->required)->toBeTrue();
    $this->actingAs($this->daniela)->post(route('cards.fields.image', [$this->card, $field]), ['image' => UploadedFile::fake()->image('ref.jpg')])->assertSessionHasNoErrors();
    $value = $this->card->fieldValues()->where('custom_field_id', $field->id)->first();
    expect($value->value)->not->toBeNull();
    $this->actingAs($this->daniela)->get(route('projects.show', [$this->project, 'tarjeta' => $this->card->id]))
        ->assertInertia(fn ($p) => $p->where("card.values.{$field->id}", fn ($v) => str_contains((string) $v, '/adjuntos/')));
    $this->actingAs($this->daniela)->put(route('cards.fields', $this->card), ['values' => [$field->id => null]]);
    expect($this->card->fieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse()
        ->and($this->card->allAttachments()->count())->toBe(0);
});

test('the board page loads the activity feed only on demand and offers the other boards for duplicating', function () {
    $this->actingAs($this->daniela)->get(route('projects.show', $this->project))->assertInertia(fn ($p) => $p->missing('activity')->has('all_projects', 1));
    $this->actingAs($this->daniela)->put(route('cards.complete', $this->card));
    $version = app(ResponseFactory::class)->getVersion();
    $this->actingAs($this->daniela)->get(route('projects.show', $this->project), ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'projects/show', 'X-Inertia-Partial-Data' => 'activity', 'X-Inertia-Version' => (string) $version])
        ->assertOk()->assertJsonPath('props.activity.0.text', 'completó la tarjeta');
});

test('admins upload a client logo that any signed-in user can see', function () {
    $admin = userWithRole(Role::Admin);
    $client = Client::where('name', 'Cencosud')->first();
    $this->actingAs($admin)->post(route('admin.clients.logo.store', $client), ['logo' => UploadedFile::fake()->image('logo.png')])->assertSessionHas('success');
    expect($client->fresh()->logo_path)->not->toBeNull();
    $this->actingAs(userWithRole(Role::Marketing))->get(route('clients.logo', $client))->assertOk();
    $this->actingAs($admin)->delete(route('admin.clients.logo.destroy', $client))->assertSessionHas('success');
    expect($client->fresh()->logo_path)->toBeNull();
});

test('activity broadcasts to the board so teammates get a toast', function () {
    $this->actingAs($this->daniela)->put(route('cards.complete', $this->card));
    Event::assertDispatched(ActivityLogged::class, fn ($e) => $e->text === 'completó la tarjeta' && $e->by->id === $this->daniela->id);
});
