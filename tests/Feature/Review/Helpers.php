<?php

use App\Actions\Review\ApproveForPrint;
use App\Actions\Review\ValidatePage;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Sla\SlaSettings;
use App\Enums\Role;
use App\Models\Page;
use App\Models\Sector;
use App\Models\User;
use Carbon\CarbonImmutable;

/** A Wednesday morning inside business hours, in Bogotá, with the prototype's SLA the clock tests were written against. */
function openHours(string $time = '10:00'): void
{
    test()->travelTo(CarbonImmutable::parse("2026-09-16 {$time}", 'America/Bogota'));
    prototypeSla();
}

/** The original per-type deadlines (production now uses five hours for everything, see SlaSettings). */
function prototypeSla(): void
{
    app(SettingsRepository::class)->putSla(new SlaSettings(
        ['precio' => 30, 'descripcion' => 45, 'imagen' => 120, 'diseno' => 240, 'otro' => 60], 120, ['marketing' => 240, 'planificacion' => 240, 'comercial' => 480],
    ));
}

function closedHours(): void
{
    test()->travelTo(CarbonImmutable::parse('2026-09-16 20:00', 'America/Bogota'));
}

function userWithRole(Role $role, array $sectors = []): User
{
    $user = User::factory()->role($role)->create();
    if ($sectors) {
        $user->sectors()->sync(Sector::whereIn('slug', $sectors)->pluck('id'));
    }

    return $user;
}

function foodPage(): Page
{
    return Page::factory()->sectors(['liquidos'])->create();
}

/** Validation up (M, P, C) and print approval down (C, P, M): the page ends closed. */
function closeWholeChain(Page $page, ?User $marketing = null, ?User $planning = null, ?User $commercial = null): void
{
    $marketing ??= userWithRole(Role::Marketing);
    $planning ??= userWithRole(Role::Planificacion);
    $commercial ??= userWithRole(Role::Comercial, ['liquidos']);
    app(ValidatePage::class)->handle($marketing, $page->fresh());
    app(ValidatePage::class)->handle($planning, $page->fresh());
    app(ValidatePage::class)->handle($commercial, $page->fresh());
    app(ApproveForPrint::class)->handle($commercial, $page->fresh());
    app(ApproveForPrint::class)->handle($planning, $page->fresh());
    app(ApproveForPrint::class)->handle($marketing, $page->fresh());
    $page->refresh();
}
