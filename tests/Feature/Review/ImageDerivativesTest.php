<?php

use App\Actions\Review\UploadVersion;
use App\Enums\Role;
use App\Jobs\ProcessPageVersionImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('uploading a version queues the image processing and the page falls back to the original meanwhile', function () {
    Storage::fake('local');
    Queue::fake();
    $page = foodPage();

    $version = app(UploadVersion::class)->handle(userWithRole(Role::Agencia), $page, UploadedFile::fake()->image('big.jpg', 3213, 6260));

    Queue::assertPushed(ProcessPageVersionImage::class, fn ($job) => $job->version->is($version));
    expect($version->display_path)->toBeNull()
        ->and($version->displayUrl())->toBe(route('images.show', ['path' => $version->image_path]));
});

test('the job derives a 1400 px screen copy and a 360 px thumbnail next to the original', function () {
    Storage::fake('local');
    $page = foodPage();
    $version = app(UploadVersion::class)->handle(userWithRole(Role::Agencia), $page, UploadedFile::fake()->image('big.jpg', 2000, 4000));

    (new ProcessPageVersionImage($version))->handle();
    $version->refresh();

    Storage::disk('local')->assertExists($version->display_path);
    Storage::disk('local')->assertExists($version->thumb_path);
    [$dw] = getimagesizefromstring(Storage::disk('local')->get($version->display_path));
    [$tw] = getimagesizefromstring(Storage::disk('local')->get($version->thumb_path));
    expect($dw)->toBe(1400)->and($tw)->toBe(360)
        ->and($version->displayUrl())->toContain('-display.jpg')
        ->and($version->thumbUrl())->toContain('-thumb.jpg');
});

test('the sidebar receives the review round progress', function () {
    $page = foodPage();
    $this->actingAs(userWithRole(Role::Marketing))->get(route('catalogs.index'))
        ->assertInertia(fn ($p) => $p->where('round.pages', 1)->where('round.approved', 0));
});
