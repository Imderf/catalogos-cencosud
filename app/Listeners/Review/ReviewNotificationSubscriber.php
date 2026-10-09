<?php

namespace App\Listeners\Review;

use App\Domain\Review\Alerts;
use App\Domain\Review\PageStatus;
use App\Enums\Role;
use App\Events\Review\AdjustmentCreated;
use App\Events\Review\AdjustmentResolved;
use App\Events\Review\AdjustmentValidated;
use App\Events\Review\CatalogPublished;
use App\Events\Review\PageApproved;
use App\Events\Review\PageReopenedAsDefect;
use App\Events\Review\PagesSubmitted;
use App\Events\Review\ReviewChanged;
use App\Events\Review\VersionUploaded;
use App\Models\Page;
use App\Notifications\Review\AlertPayload;
use Illuminate\Events\Dispatcher;

/**
 * Every state transition tells the role that has to act next.
 * Mirrors docs/prototipo/README.md §8.
 */
class ReviewNotificationSubscriber
{
    public function __construct(private readonly Alerts $alerts, private readonly PageStatus $status) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            CatalogPublished::class => 'onCatalogPublished',
            PagesSubmitted::class => 'onPagesSubmitted',
            AdjustmentCreated::class => 'onAdjustmentCreated',
            AdjustmentValidated::class => 'onAdjustmentValidated',
            AdjustmentResolved::class => 'onAdjustmentResolved',
            VersionUploaded::class => 'onVersionUploaded',
            PageApproved::class => 'onPageApproved',
            PageReopenedAsDefect::class => 'onPageReopened',
        ];
    }

    /** Uploading is Ogilvy's own work: nothing to tell the client yet. */
    public function onCatalogPublished(CatalogPublished $e): void
    {
        ReviewChanged::dispatch('carga', $e->catalog->slug);
    }

    public function onPagesSubmitted(PagesSubmitted $e): void
    {
        $c = $e->catalog;
        $n = $e->pages->count();
        $first = $e->pages->sortBy('number')->first();
        $text = $n === 1
            ? 'Ogilvy envió la '.$first->label().' de '.$c->name.' a revisión · empieza en Marketing'
            : 'Ogilvy envió '.$n.' páginas de '.$c->name.' a revisión · empieza en Marketing';
        $this->alerts->toRoles([Role::Marketing], AlertPayload::about('carga', $text, $e->by->name, $c, $n === 1 ? $first : null));
        ReviewChanged::dispatch('carga', $c->slug);
    }

    public function onAdjustmentCreated(AdjustmentCreated $e): void
    {
        $a = $e->adjustment;
        if ($a->is_defect) {
            return; // PageReopenedAsDefect carries this one
        }
        $page = $a->page;
        $this->alerts->toRoles([$a->currentStage()], AlertPayload::about(
            'ajuste', 'Ajuste de '.$a->typeShort().' en la '.$page->label().' de '.$page->catalog->name, $e->by->name, $page->catalog, $page,
        ), $page);
        ReviewChanged::dispatch('ajuste', $page->catalog->slug, $page->number);
    }

    public function onAdjustmentValidated(AdjustmentValidated $e): void
    {
        $a = $e->adjustment;
        $page = $a->page;
        $this->alerts->toRoles([$a->currentStage()], AlertPayload::about(
            'validacion', $e->by->role()->short().' validó un ajuste de '.$a->typeShort().' en la '.$page->label(), $e->by->name, $page->catalog, $page,
        ), $page);
        ReviewChanged::dispatch('validacion', $page->catalog->slug, $page->number);
    }

    public function onAdjustmentResolved(AdjustmentResolved $e): void
    {
        $page = $e->adjustment->page->fresh(['adjustments', 'approvals']);
        ReviewChanged::dispatch('resuelto', $page->catalog->slug, $page->number);
        if ($this->status->hasOpenAdjustments($page)) {
            return;
        }
        $stage = $this->status->approvalStage($page);
        if ($stage === null) {
            return;
        }
        $this->alerts->toRoles([$stage], AlertPayload::about(
            'resuelto', 'Ogilvy resolvió los ajustes de la '.$page->label().' · pendiente tu aprobación', $e->by->name, $page->catalog, $page,
        ), $page);
    }

    public function onVersionUploaded(VersionUploaded $e): void
    {
        $page = $e->version->page;
        $this->alerts->toRoles([Role::Marketing], AlertPayload::about(
            'version', 'Ogilvy cargó la v'.$e->version->number.' de la '.$page->label().' · vuelve a revisión', $e->by->name, $page->catalog, $page,
        ), $page);
        ReviewChanged::dispatch('version', $page->catalog->slug, $page->number);
    }

    public function onPageApproved(PageApproved $e): void
    {
        /** @var Page $page */
        $page = $e->approval->page->fresh(['approvals', 'adjustments']);
        $next = $this->status->approvalStage($page);
        $role = $e->approval->role;
        $print = ($e->approval->phase ?? 'validation') === 'print';
        ReviewChanged::dispatch('aprobacion', $page->catalog->slug, $page->number);
        if ($next !== null) {
            $verb = $print ? 'aprobó' : 'validó';
            /* Only Marketing, the last link, actually approves for print; the others just approve. */
            $todo = $this->status->phase($page) === PageStatus::PRINTING ? ($next === Role::Marketing ? 'te toca aprobar a imprenta' : 'te toca aprobar') : 'ahora te toca validar';
            $this->alerts->toRoles([$next], AlertPayload::about(
                'aprobacion', $role->short().' '.$verb.' la '.$page->label().' · '.$todo, $e->by->name, $page->catalog, $page,
            ), $page);
        } else {
            $this->alerts->toRoles([Role::Agencia, Role::Marketing], AlertPayload::about(
                'aprobacion', $page->label().' aprobada a imprenta por toda la cadena', $e->by->name, $page->catalog, $page,
            ), $page, $e->by);
        }
    }

    public function onPageReopened(PageReopenedAsDefect $e): void
    {
        $page = $e->defect->page;
        $this->alerts->toRoles([Role::Agencia], AlertPayload::about(
            'defecto', 'Post Production Defect en la '.$page->label().' · página reabierta', $e->by->name, $page->catalog, $page,
        ), $page);
        ReviewChanged::dispatch('defecto', $page->catalog->slug, $page->number);
    }
}
