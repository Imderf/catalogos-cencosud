<?php

namespace App\Policies;

use App\Domain\Time\BusinessTime;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AdjustmentPolicy
{
    public function __construct(private readonly BusinessTime $time) {}

    /** An intermediate client link validates and passes the adjustment down. */
    public function validate(User $user, Adjustment $adjustment): Response
    {
        if ($adjustment->page->catalog->isArchived()) {
            return Response::deny('Este catálogo está archivado.');
        }
        if (! $adjustment->isOpen()) {
            return Response::deny('El ajuste ya está resuelto.');
        }
        $stage = $adjustment->currentStage();
        if ($stage === Role::Agencia) {
            return Response::deny('El ajuste ya está en Ogilvy.');
        }
        if ($stage !== $user->role()) {
            return Response::deny('Este ajuste está en '.$stage->label().', no en tu equipo.');
        }
        if (! $user->canActOn($adjustment->page)) {
            return Response::deny('Esta página es de otra categoría.');
        }
        if (! $this->time->actionsAllowedFor($user)) {
            return Response::deny('Fuera de horario laboral: no se puede validar hasta que se reanude la jornada.');
        }

        return Response::allow();
    }

    /** Ogilvy marks the adjustment as executed. */
    public function resolve(User $user, Adjustment $adjustment): Response
    {
        if (! $user->can('catalogs.manage')) {
            return Response::deny('Solo Ogilvy resuelve ajustes.');
        }
        if ($adjustment->page->catalog->isArchived()) {
            return Response::deny('Este catálogo está archivado.');
        }
        if (! $adjustment->isOpen()) {
            return Response::deny('El ajuste ya está resuelto.');
        }
        if ($adjustment->currentStage() !== Role::Agencia) {
            return Response::deny('El ajuste todavía no llegó a Ogilvy: está en '.$adjustment->currentStage()->label().'.');
        }
        if (! $this->time->actionsAllowedFor($user)) {
            return Response::deny('Fuera de horario laboral.');
        }

        return Response::allow();
    }

    /** The author may correct an adjustment until the next link validates it. */
    public function update(User $user, Adjustment $adjustment): Response
    {
        if ($adjustment->page->catalog->isArchived()) {
            return Response::deny('Este catálogo está archivado.');
        }
        if ($adjustment->author_id !== $user->id) {
            return Response::deny('Solo quien pidió el ajuste puede editarlo.');
        }
        if (! $adjustment->isOpen()) {
            return Response::deny('El ajuste ya está resuelto.');
        }
        if ($adjustment->validations()->exists()) {
            return Response::deny('El ajuste ya fue validado por el siguiente eslabón; agrega una respuesta en vez de editarlo.');
        }
        if (! $this->time->actionsAllowedFor($user)) {
            return Response::deny('Fuera de horario laboral.');
        }

        return Response::allow();
    }

    /** Same window as update, except a Post Production Defect cannot be withdrawn: the page is already reopened. */
    public function delete(User $user, Adjustment $adjustment): Response
    {
        $r = $this->update($user, $adjustment);
        if ($r->denied()) {
            return $r;
        }
        if ($adjustment->is_defect) {
            return Response::deny('Un defecto post-producción no se retira: ya reabrió la página.');
        }

        return Response::allow();
    }

    /** Anyone involved can add a note to the thread. */
    public function reply(User $user, Adjustment $adjustment): Response
    {
        if ($adjustment->page->catalog->isArchived()) {
            return Response::deny('Este catálogo está archivado.');
        }
        if ($user->isReadOnly()) {
            return Response::deny('Tu acceso es de solo lectura.');
        }
        if ($user->actsAsAgency() || $user->canActOn($adjustment->page)) {
            return Response::allow();
        }

        return Response::deny('Esta página es de otra categoría.');
    }
}
