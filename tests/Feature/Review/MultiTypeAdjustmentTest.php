<?php

use App\Actions\Review\CreateAdjustment;
use App\Domain\Review\Clocks;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Support\Box;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('an adjustment can be of several kinds at once; its clock takes the longest of them', function () {
    $page = foodPage();
    $marketing = userWithRole(Role::Marketing);
    $box = ['x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.2];

    $this->actingAs($marketing)->post(route('pages.adjustments.store', [$page->catalog->slug, $page->number]), ['types' => ['precio', 'imagen'], 'text' => 'Precio y foto', 'box' => $box])
        ->assertSessionHasNoErrors();
    $a = $page->adjustments()->first();
    expect($a->type)->toBe(AdjustmentType::Precio)->and($a->types)->toBe(['precio', 'imagen'])
        ->and($a->typeLabel())->toBe('Precio + Imagen')
        ->and(app(Clocks::class)->adjustmentSla($a))->toBe(120); // imagen (120) beats precio (30) in the prototype SLA

    $this->actingAs($marketing)->get(route('pages.show', [$page->catalog->slug, $page->number]))
        ->assertInertia(fn ($p) => $p->where('page.adjustments.0.types', ['precio', 'imagen'])->where('page.adjustments.0.type_label', 'Precio + Imagen'));

    // the author can change the set; an empty set is refused; the old single `type` field still works
    $this->actingAs($marketing)->put(route('adjustments.update', $a), ['types' => ['diseno'], 'text' => 'Solo diseño'])->assertSessionHasNoErrors();
    expect($a->fresh()->types)->toBe(['diseno'])->and($a->fresh()->type)->toBe(AdjustmentType::Diseno);
    $this->actingAs($marketing)->put(route('adjustments.update', $a), ['types' => [], 'text' => 'x'])->assertSessionHasErrors('types');
    $this->actingAs($marketing)->put(route('adjustments.update', $a), ['type' => 'otro', 'text' => 'x'])->assertSessionHasNoErrors();
    expect($a->fresh()->types)->toBe(['otro']);

    // through the action, a list is accepted and de-duplicated
    $b = app(CreateAdjustment::class)->handle($marketing, $page->fresh(), [AdjustmentType::Otro, AdjustmentType::Otro, AdjustmentType::Precio], 'y', Box::fromArray($box));
    expect($b->types)->toBe(['otro', 'precio']);

    // Cencosud's own kinds are available too
    $this->actingAs($marketing)->post(route('pages.adjustments.store', [$page->catalog->slug, $page->number]), ['types' => ['dinamica', 'ingresa_dinamica', 'compaginada', 'pata'], 'text' => 'z', 'box' => $box])->assertSessionHasNoErrors();
    expect($page->adjustments()->reorder('id', 'desc')->first()->typeLabel())->toBe('Dinámica + Ingresa Dinámica + Compaginada + Pata');
});
