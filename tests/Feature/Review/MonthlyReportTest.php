<?php

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\ResolveAdjustment;
use App\Actions\Review\ValidatePage;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Models\Catalog;
use App\Models\Page;
use App\Support\Box;

require_once __DIR__.'/Helpers.php';

beforeEach(fn () => openHours());

test('the monthly report consolidates the catalogs published that month', function () {
    $sep = Catalog::factory()->create(['name' => 'Jumbo Sep', 'published_at' => now()]);
    $page = Page::factory()->sectors(['liquidos'])->create(['catalog_id' => $sep->id, 'number' => 1]);
    Catalog::factory()->create(['name' => 'Agosto', 'published_at' => now()->subMonth()]);

    $valeria = userWithRole(Role::Marketing);
    $agency = userWithRole(Role::Agencia);
    $adj = app(CreateAdjustment::class)->handle($valeria, $page, AdjustmentType::Precio, 'x', Box::defectDefault());
    test()->travelTo(now()->addMinutes(20));
    app(ResolveAdjustment::class)->handle($agency, $adj);
    test()->travelTo(now()->addMinutes(250)); // marketing approves 10 min late
    app(ValidatePage::class)->handle($valeria, $page->fresh());

    $this->actingAs($valeria)->get(route('reports.monthly', ['mes' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('reports/monthly')
            ->where('month', '2026-09')
            ->has('rows', 1)
            ->where('rows.0.name', 'Jumbo Sep')
            ->where('rows.0.total', 1)
            ->where('rows.0.avg_execution', 20)
            ->where('rows.0.avg_delay', 10)
            ->where('kpis.catalogs', 1)
            ->where('kpis.avg_execution', 20));

    $this->actingAs($valeria)->get(route('reports.monthly', ['mes' => '2026-08']))
        ->assertInertia(fn ($p) => $p->has('rows', 1)->where('rows.0.name', 'Agosto'));

    $this->actingAs($valeria)->get(route('reports.monthly.csv', ['mes' => '2026-09']))
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

test('an invalid month falls back to the current one', function () {
    $this->actingAs(userWithRole(Role::Marketing))->get(route('reports.monthly', ['mes' => 'nope']))
        ->assertInertia(fn ($p) => $p->where('month', '2026-09'));
});
