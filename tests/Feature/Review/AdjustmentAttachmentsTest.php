<?php

use App\Domain\Projects\CatalogCards;
use App\Enums\Role;
use App\Events\Projects\ActivityLogged;
use App\Events\Projects\BoardChanged;
use App\Models\AdjustmentIntake;
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
    $this->catalog = Catalog::factory()->create(['name' => 'Jumbo Octavilla']);
    $this->page = Page::factory()->create(['catalog_id' => $this->catalog->id, 'number' => 1]);
    $this->page->sectors()->sync([Sector::where('slug', 'liquidos')->first()->id]);
});

$box = ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2];

test('the client can attach a PDF, an image and a link to an adjustment, and everyone on the page sees them', function () use ($box) {
    $this->actingAs($this->marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, 1]), [
        'type' => 'imagen', 'text' => 'Usar la foto nueva del brief', 'box' => $box,
        'files' => [UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('foto.png', 200, 200)],
        'url' => 'https://drive.google.com/drive/folders/abc',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $a = $this->page->adjustments()->first();
    expect($a->attachments)->toHaveCount(3)
        ->and($a->attachments->pluck('kind')->all())->toBe(['link', 'file', 'file'])
        ->and($a->attachments[1]->name)->toBe('brief.pdf')
        ->and($a->attachments[2]->isImage())->toBeTrue();
    Storage::disk('local')->assertExists($a->attachments[1]->path);
    expect($a->attachments[1]->path)->toStartWith("catalogos/{$this->catalog->slug}/ajustes/{$a->id}/");

    // Ogilvy sees them in the page and can open the file; the link goes out as is
    $this->actingAs($this->daniela)->get(route('pages.show', [$this->catalog->slug, 1]))
        ->assertInertia(fn ($p) => $p->count('page.adjustments.0.attachments', 3)
            ->where('page.adjustments.0.attachments.0.url', 'https://drive.google.com/drive/folders/abc')
            ->where('page.adjustments.0.attachments.2.image', true));
    $this->actingAs($this->daniela)->get(route('adjustments.attachments.show', [$a->id, $a->attachments[1]->id]))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('only PDFs and images are accepted, and a bad link is rejected', function () use ($box) {
    $this->actingAs($this->marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, 1]), [
        'type' => 'precio', 'text' => 'x', 'box' => $box, 'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
    ])->assertSessionHasErrors('files.0');
    $this->actingAs($this->marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, 1]), [
        'type' => 'precio', 'text' => 'x', 'box' => $box, 'url' => 'no es un enlace',
    ])->assertSessionHasErrors('url');
    expect($this->page->adjustments()->count())->toBe(0);
});

test('withdrawing the adjustment removes its files', function () use ($box) {
    $this->actingAs($this->marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, 1]), [
        'type' => 'precio', 'text' => 'x', 'box' => $box, 'files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')],
    ])->assertSessionHasNoErrors();
    $a = $this->page->adjustments()->first();
    $path = $a->attachments[0]->path;
    $this->actingAs($this->marketing)->delete(route('adjustments.destroy', $a))->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($path);
});

test('when Ogilvy approves the intake, the files and the link travel with the client comment into the subtask', function () use ($box) {
    $project = Project::factory()->create(['owner_id' => $this->daniela->id]);
    $project->members()->attach($this->daniela, ['role' => 'manager']);
    $project->lists()->create(['name' => 'Backlog', 'kind' => 'backlog', 'position' => 1024]);
    app(CatalogCards::class)->link($this->catalog, $project, $this->daniela);

    $this->actingAs($this->marketing)->post(route('pages.adjustments.store', [$this->catalog->slug, 1]), [
        'type' => 'precio', 'text' => 'Ver referencia', 'box' => $box,
        'files' => [UploadedFile::fake()->image('ref.jpg', 100, 100)], 'url' => 'https://ejemplo.com/recursos',
    ])->assertSessionHasNoErrors();
    $a = $this->page->adjustments()->first();
    $intake = AdjustmentIntake::where('adjustment_id', $a->id)->firstOrFail();
    $this->actingAs($this->daniela)->post(route('intakes.approve', $intake))->assertSessionHasNoErrors();

    $comment = $intake->subtask->comments()->first();
    expect($comment->body)->toContain('Precio: Ver referencia')->toContain("\nhttps://ejemplo.com/recursos")
        ->and($comment->attachments)->toHaveCount(1)
        ->and($comment->attachments[0]->name)->toBe('ref.jpg')
        ->and($comment->attachments[0]->path)->toStartWith("proyectos/{$project->id}/tarjetas/");
    Storage::disk('local')->assertExists($comment->attachments[0]->path);
    Storage::disk('local')->assertExists($a->attachments->firstWhere('kind', 'file')->path); // the review keeps its own copy
    $this->actingAs($this->daniela)->get(route('attachments.show', $comment->attachments[0]->id))->assertOk();
});
