<?php

namespace App\Domain\Review;

use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Approval;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Two passes over every version, recorded in `approvals` with a phase:
 *   validation ASCENDS: Marketing → Planificación → Comercial ("Validar")
 *   print DESCENDS:     Comercial → Planificación → Marketing ("Aprobar a imprenta")
 * When Marketing approves for print the page is closed. A new version restarts both.
 * A page is never in two states at once: while an adjustment is open the chain is suspended.
 */
class PageStatus
{
    public const VALIDATION = Approval::VALIDATION;

    public const PRINTING = Approval::PRINTING;

    public const CLOSED = 'closed';

    public const PREPARATION = 'preparation';

    /** @return list<Role> */
    public static function validationOrder(): array
    {
        return Role::chain();
    }

    /** @return list<Role> */
    public static function printOrder(): array
    {
        return array_reverse(Role::chain());
    }

    private function done(Page $page, string $phase): array
    {
        return $page->approvals
            ->filter(fn ($a) => $a->version === $page->current_version && ($a->phase ?? self::VALIDATION) === $phase)
            ->map(fn ($a) => $a->role->value)->values()->all();
    }

    /** preparation | validation | print | closed */
    public function phase(Page $page): string
    {
        if (! $page->isSubmitted()) {
            return self::PREPARATION;
        }
        if ($this->validationStage($page) !== null) {
            return self::VALIDATION;
        }

        return $this->printStage($page) !== null ? self::PRINTING : self::CLOSED;
    }

    /** Role whose "Validar" is due; null once the three validated. */
    public function validationStage(Page $page): ?Role
    {
        if (! $page->isSubmitted()) {
            return null;
        }
        $done = $this->done($page, self::VALIDATION);
        foreach (self::validationOrder() as $role) {
            if (! in_array($role->value, $done, true)) {
                return $role;
            }
        }

        return null;
    }

    /** Role whose "Aprobar a imprenta" is due; null before validation ends or once closed. */
    public function printStage(Page $page): ?Role
    {
        if (! $page->isSubmitted() || $this->validationStage($page) !== null) {
            return null;
        }
        $done = $this->done($page, self::PRINTING);
        foreach (self::printOrder() as $role) {
            if (! in_array($role->value, $done, true)) {
                return $role;
            }
        }

        return null;
    }

    /** Role that holds the ball in whichever phase is running; null when closed. */
    public function approvalStage(Page $page): ?Role
    {
        return $this->validationStage($page) ?? $this->printStage($page);
    }

    /** Closed: validated by the three and approved for print by the three. */
    public function isApproved(Page $page): bool
    {
        return $this->phase($page) === self::CLOSED;
    }

    /** @return Collection<int, Adjustment> */
    public function openAdjustments(Page $page): Collection
    {
        return $page->adjustments->filter(fn (Adjustment $a) => $a->isOpen())->values();
    }

    public function hasOpenAdjustments(Page $page): bool
    {
        return $this->openAdjustments($page)->isNotEmpty();
    }

    /** While adjustments are open the chain (and its clock) is suspended. */
    public function approvalSuspended(Page $page): bool
    {
        return $this->hasOpenAdjustments($page);
    }

    /** Whether this user's role already validated the current version. */
    public function hasApproved(Page $page, User $user): bool
    {
        return $this->hasValidated($page, $user);
    }

    public function hasValidated(Page $page, User $user): bool
    {
        $role = $user->role();

        return $role !== null && in_array($role->value, $this->done($page, self::VALIDATION), true);
    }

    public function hasApprovedForPrint(Page $page, User $user): bool
    {
        $role = $user->role();

        return $role !== null && in_array($role->value, $this->done($page, self::PRINTING), true);
    }

    public function lastApproval(Page $page)
    {
        return $page->approvals->where('version', $page->current_version)->sortBy('created_at')->last();
    }

    public function location(Page $page): Location
    {
        $open = $this->openAdjustments($page);
        if ($open->isNotEmpty()) {
            $roles = $open->map(fn (Adjustment $a) => $a->currentStage())->unique()->values();
            if ($roles->count() > 1) {
                return new Location(null, 'En varios equipos', true);
            }
            /** @var Role $role */
            $role = $roles->first();

            return new Location($role, 'En '.$role->short(), true);
        }
        $phase = $this->phase($page);
        if ($phase === self::PREPARATION) {
            return new Location(Role::Agencia, 'En Ogilvy · preparación', true);
        }
        if ($phase === self::CLOSED) {
            return new Location(null, 'Aprobada a imprenta', false, true);
        }
        $stage = $this->approvalStage($page);

        return new Location($stage, ($phase === self::PRINTING ? 'Imprenta · ' : 'Validación · ').$stage->short(), false);
    }
}
