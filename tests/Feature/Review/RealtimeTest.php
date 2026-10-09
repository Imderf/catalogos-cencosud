<?php

use App\Actions\Review\CreateAdjustment;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Events\Review\ReviewChanged;
use App\Support\Box;
use Illuminate\Support\Facades\Event;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('every transition broadcasts a change on the private review channel', function () {
    Event::fake([ReviewChanged::class]);
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);

    app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', Box::defectDefault());
    Event::assertDispatched(ReviewChanged::class, fn ($e) => $e->kind === 'ajuste' && $e->catalog === $page->catalog->slug && $e->page === $page->number);

    $event = new ReviewChanged('aprobacion', 'jumbo', 3);
    expect($event->broadcastOn()[0]->name)->toBe('private-review')
        ->and($event->broadcastAs())->toBe('review.changed')
        ->and($event->broadcastWith())->toBe(['kind' => 'aprobacion', 'catalog' => 'jumbo', 'page' => 3]);
});

test('only active users may join the review channel', function () {
    /* The log driver never checks channels; the Reverb driver does. */
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', '1');
    require base_path('routes/channels.php'); // channels register on the driver active at load time
    $user = userWithRole(Role::Marketing);
    $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => 'private-review', 'socket_id' => '1234.5678'])->assertOk();
    $user->update(['is_active' => false]);
    $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => 'private-review', 'socket_id' => '1234.5678'])->assertForbidden();
});
