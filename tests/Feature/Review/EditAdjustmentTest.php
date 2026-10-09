<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\DeleteAdjustment;
use App\Actions\Review\ReopenAsDefect;
use App\Actions\Review\UpdateAdjustment;
use App\Actions\Review\ValidateAdjustment;
use App\Domain\Review\PageStatus;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Support\Box;
use Illuminate\Auth\Access\AuthorizationException;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

$box = fn () => Box::fromArray(['x' => .1, 'y' => .2, 'w' => .3, 'h' => .1]);

test('the author edits text and type until someone validates', function () use ($box) {
    $page = foodPage();
    $hernan = userWithRole(Role::Comercial, ['liquidos']);
    $other = userWithRole(Role::Comercial, ['liquidos']);
    $adj = app(CreateAdjustment::class)->handle($hernan, $page, AdjustmentType::Precio, 'Precio mal', $box());

    $adj = app(UpdateAdjustment::class)->handle($hernan, $adj, AdjustmentType::Descripcion, 'Falta el gramaje');
    expect($adj->type)->toBe(AdjustmentType::Descripcion)->and($adj->text)->toBe('Falta el gramaje');

    expect(fn () => app(UpdateAdjustment::class)->handle($other, $adj, AdjustmentType::Otro, 'x'))
        ->toThrow(AuthorizationException::class, 'Solo quien pidió el ajuste');

    app(ValidateAdjustment::class)->handle(userWithRole(Role::Planificacion), $adj);
    expect(fn () => app(UpdateAdjustment::class)->handle($hernan, $adj->fresh(), AdjustmentType::Otro, 'x'))
        ->toThrow(AuthorizationException::class, 'ya fue validado');
});

test('withdrawing the last open adjustment resumes the approval clock', function () use ($box) {
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    $adj = app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', $box());
    test()->travelTo(now()->addMinutes(30));

    app(DeleteAdjustment::class)->handle($valeria, $adj);

    expect(Adjustment::count())->toBe(0)
        ->and(app(PageStatus::class)->hasOpenAdjustments($page->fresh()))->toBeFalse()
        ->and($page->fresh()->waiting_since->equalTo(now()))->toBeTrue();
});

test('a defect can be reworded but not withdrawn', function () {
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    closeWholeChain($page, $valeria);
    $defect = app(ReopenAsDefect::class)->handle($valeria, $page->fresh(), AdjustmentType::Imagen, 'Salió mal');

    app(UpdateAdjustment::class)->handle($valeria, $defect->adjustment, AdjustmentType::Imagen, 'Salió con el empaque viejo');
    expect($defect->adjustment->fresh()->text)->toBe('Salió con el empaque viejo');
    expect(fn () => app(DeleteAdjustment::class)->handle($valeria, $defect->adjustment->fresh()))
        ->toThrow(AuthorizationException::class, 'no se retira');
});

test('edit and delete go through HTTP with the domain messages', function () use ($box) {
    $page = foodPage();
    $valeria = userWithRole(Role::Marketing);
    $adj = app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', $box());

    $this->actingAs($valeria)->put(route('adjustments.update', $adj), ['type' => 'otro', 'text' => 'Nuevo texto'])->assertSessionHas('success');
    expect($adj->fresh()->text)->toBe('Nuevo texto');
    $this->actingAs(userWithRole(Role::Planificacion))->delete(route('adjustments.destroy', $adj))->assertSessionHas('error');
    $this->actingAs($valeria)->delete(route('adjustments.destroy', $adj))->assertSessionHas('success');
    expect(Adjustment::count())->toBe(0);
});
