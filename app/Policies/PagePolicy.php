<?php

namespace App\Policies;

use App\Domain\Review\PageStatus;
use App\Domain\Time\BusinessTime;
use App\Enums\Role;
use App\Models\Page;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Every rule of the review model, enforced on the server. The front only mirrors these.
 */
class PagePolicy
{
    public function __construct(private readonly PageStatus $status, private readonly BusinessTime $time) {}

    public function view(User $user, Page $page): bool
    {
        return true; // everybody sees the whole project; acting is what is restricted
    }

    /** A client draws a region and asks for an adjustment, only during the validation pass. */
    public function comment(User $user, Page $page): Response
    {
        if (! $user->isClient()) {
            return Response::deny('Solo el cliente pide ajustes; Ogilvy los ejecuta.');
        }
        if ($r = $this->sectorAndHours($user, $page)) {
            return $r;
        }
        $phase = $this->status->phase($page);
        if ($phase === PageStatus::PREPARATION) {
            return Response::deny('Ogilvy todavía no envió esta página a revisión.');
        }
        if ($phase === PageStatus::CLOSED) {
            return Response::deny('La página ya está aprobada a imprenta. Marketing puede reabrirla como Post Production Defect.');
        }
        if ($phase === PageStatus::PRINTING) {
            return Response::deny('La página ya fue validada por toda la cadena y está en aprobación a imprenta.');
        }
        if ($this->status->hasValidated($page, $user)) {
            return Response::deny('Ya validaste esta versión; el siguiente eslabón la tiene ahora.');
        }

        return Response::allow();
    }

    /** "Validar": only the role whose turn it is, only with no open adjustments. */
    public function validate(User $user, Page $page): Response
    {
        if (! $user->isClient()) {
            return Response::deny('Solo el cliente valida páginas.');
        }
        if ($r = $this->sectorAndHours($user, $page)) {
            return $r;
        }
        if (! $page->isSubmitted()) {
            return Response::deny('Ogilvy todavía no envió esta página a revisión.');
        }
        $stage = $this->status->validationStage($page);
        if ($stage === null) {
            return Response::deny('La página ya fue validada por toda la cadena.');
        }
        if ($stage !== $user->role()) {
            return Response::deny('Ahora le toca validar a '.$stage->label().'.');
        }
        if ($this->status->hasOpenAdjustments($page)) {
            return Response::deny('Hay ajustes en curso. La validación se reanuda cuando Ogilvy los cierre.');
        }

        return Response::allow();
    }

    /** "Aprobar a imprenta": descends after the three validations; Marketing closes the page. */
    public function approvePrint(User $user, Page $page): Response
    {
        if (! $user->isClient()) {
            return Response::deny('Solo el cliente aprueba a imprenta.');
        }
        if ($r = $this->sectorAndHours($user, $page)) {
            return $r;
        }
        $phase = $this->status->phase($page);
        if ($phase === PageStatus::PREPARATION) {
            return Response::deny('Ogilvy todavía no envió esta página a revisión.');
        }
        if ($phase === PageStatus::VALIDATION) {
            return Response::deny('Se habilita cuando Marketing, Planificación y Comercial hayan validado la página.');
        }
        if ($phase === PageStatus::CLOSED) {
            return Response::deny('La página ya está aprobada a imprenta.');
        }
        $stage = $this->status->printStage($page);
        if ($stage !== $user->role()) {
            return Response::deny('Ahora le toca aprobar a '.$stage->label().'.');
        }
        if ($this->status->hasOpenAdjustments($page)) {
            return Response::deny('Hay ajustes en curso. La aprobación se reanuda cuando Ogilvy los cierre.');
        }

        return Response::allow();
    }

    /** Legacy name kept for callers: same as validate. */
    public function approve(User $user, Page $page): Response
    {
        return $this->validate($user, $page);
    }

    /** Post Production Defect: only Marketing, only on a page approved for print. */
    public function reopen(User $user, Page $page): Response
    {
        if ($user->role() !== Role::Marketing) {
            return Response::deny('Solo Marketing registra defectos post-producción.');
        }
        if ($r = $this->sectorAndHours($user, $page)) {
            return $r;
        }
        if (! $this->status->isApproved($page)) {
            return Response::deny('Se habilita cuando la página esté aprobada a imprenta por toda la cadena.');
        }

        return Response::allow();
    }

    /** "Enviar a Marketing": Ogilvy finishes preparing the page and starts its chain. */
    public function submit(User $user, Page $page): Response
    {
        if (! $user->can('catalogs.manage')) {
            return Response::deny('Solo Ogilvy envía páginas a revisión.');
        }
        if ($r = $this->archived($page)) {
            return $r;
        }
        if ($page->isSubmitted()) {
            return Response::deny('Esta página ya está en revisión.');
        }

        return Response::allow();
    }

    /** Ogilvy uploads a corrected version. */
    public function uploadVersion(User $user, Page $page): Response
    {
        if (! $user->can('catalogs.manage')) {
            return Response::deny('Solo Ogilvy carga versiones.');
        }
        if ($r = $this->archived($page)) {
            return $r;
        }
        if (! $this->time->actionsAllowedFor($user)) {
            return $this->closed();
        }
        if ($this->status->isApproved($page)) {
            return Response::deny('Página aprobada a imprenta y cerrada: para volver a tocarla Marketing debe registrar un Post Production Defect.');
        }

        return Response::allow();
    }

    private function sectorAndHours(User $user, Page $page): ?Response
    {
        if ($r = $this->archived($page)) {
            return $r;
        }
        if (! $user->canActOn($page)) {
            return Response::deny('Esta página es de otra categoría: puedes verla, pero no comentarla ni validarla.');
        }
        if (! $this->time->actionsAllowedFor($user)) {
            return $this->closed();
        }

        return null;
    }

    private function archived(Page $page): ?Response
    {
        return $page->catalog->isArchived() ? Response::deny('Este catálogo está archivado. Reactívalo desde Administrar para volver a trabajarlo.') : null;
    }

    private function closed(): Response
    {
        $opens = $this->time->nextOpening();

        return Response::deny('Fuera de horario laboral ('.$this->time->hours()->label().'). Se reanuda el '.$opens->locale('es')->isoFormat('ddd D MMM [a las] HH:mm').'.');
    }
}
