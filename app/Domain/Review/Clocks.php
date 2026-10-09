<?php

namespace App\Domain\Review;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Sla\SlaSettings;
use App\Domain\Time\BusinessTime;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Page;
use Carbon\CarbonInterface;

/** Countdown logic for adjustments and approvals, all in working minutes. */
class Clocks
{
    private ?SlaSettings $sla = null;

    public function __construct(
        private readonly BusinessTime $time,
        private readonly PageStatus $status,
        private readonly SettingsRepository $settings,
    ) {}

    public function sla(): SlaSettings
    {
        return $this->sla ??= $this->settings->sla();
    }

    public function usingSla(SlaSettings $sla): static
    {
        $this->sla = $sla;

        return $this;
    }

    /** Traffic light: green while there is slack, red under 25% or overdue. */
    public function tone(?float $remaining, float $sla): string
    {
        if ($remaining === null) {
            return 'espera';
        }
        if ($remaining < 0) {
            return 'vencido';
        }

        return $remaining < $sla * 0.25 ? 'urgente' : 'corriendo';
    }

    /* ── adjustments: the deadline depends on where the adjustment is parked ── */

    public function adjustmentSla(Adjustment $a): int
    {
        return $a->currentStage() === Role::Agencia
            ? max(array_map(fn ($t) => $this->sla()->executionMinutes($t), $a->typeList()))
            : $this->sla()->validation;
    }

    public function adjustmentConsumed(Adjustment $a, ?CarbonInterface $now = null): float
    {
        return $a->stage_since ? $this->time->workingMinutesBetween($a->stage_since, $now ?? $this->time->now()) : 0.0;
    }

    public function adjustmentRemaining(Adjustment $a, ?CarbonInterface $now = null): ?float
    {
        if (! $a->isOpen() || ! $a->stage_since || $this->isQueued($a)) {
            return null;
        }

        return $this->adjustmentSla($a) - $this->adjustmentConsumed($a, $now);
    }

    public function adjustmentState(Adjustment $a, ?CarbonInterface $now = null): string
    {
        return $this->tone($this->adjustmentRemaining($a, $now), $this->adjustmentSla($a));
    }

    public function adjustmentWhere(Adjustment $a): string
    {
        $stage = $a->currentStage();
        if ($stage === null) {
            return 'Resuelto';
        }

        if ($stage === Role::Agencia) {
            return $this->isQueued($a) ? 'En cola · Ogilvy' : 'Ogilvy ejecuta';
        }

        return $stage->short().' valida';
    }

    /** Waiting for Ogilvy behind higher-priority work: the clock has not started. */
    public function isQueued(Adjustment $a): bool
    {
        return $a->isOpen() && $a->currentStage() === Role::Agencia && $a->agency_started_at === null;
    }

    /* ── approvals: the client's clock, suspended while adjustments are open ── */

    public function approvalRemaining(Page $page, ?CarbonInterface $now = null): ?float
    {
        $stage = $this->status->approvalStage($page);
        if ($stage === null || ! $page->waiting_since || $this->status->approvalSuspended($page)) {
            return null;
        }

        return $this->sla()->approvalMinutes($stage) - $this->time->workingMinutesBetween($page->waiting_since, $now ?? $this->time->now());
    }

    public function approvalOverdue(Page $page, ?CarbonInterface $now = null): bool
    {
        $r = $this->approvalRemaining($page, $now);

        return $r !== null && $r < 0;
    }

    /* ── the page clock: whoever holds the ball ── */

    public function pageClock(Page $page, ?CarbonInterface $now = null): ?PageClock
    {
        $now ??= $this->time->now();
        $frozen = ! $this->time->isOpen($now);
        $open = $this->status->openAdjustments($page);

        if ($open->isNotEmpty()) {
            /* Queued adjustments (waiting behind higher-priority tasks) have no running clock: they are counted, not timed. */
            $running = $open->reject(fn (Adjustment $a) => $this->isQueued($a));
            $queued = $open->count() - $running->count();
            $total = $running->sum(fn (Adjustment $a) => $this->adjustmentSla($a));
            $consumed = $running->sum(fn (Adjustment $a) => $this->adjustmentConsumed($a, $now));
            $remaining = $total - $consumed;
            $overdue = $running->filter(fn (Adjustment $a) => ($this->adjustmentRemaining($a, $now) ?? 0) < 0)->count();
            $state = $running->isEmpty() ? 'espera' : ($overdue > 0 ? 'vencido' : $this->tone($remaining, $total));
            $where = $open->count() === 1
                ? $this->adjustmentWhere($open->first())
                : $running->filter(fn (Adjustment $a) => $a->currentStage() === Role::Agencia)->count().' en Ogilvy · '
                  .($queued > 0 ? $queued.' en cola · ' : '')
                  .$open->filter(fn (Adjustment $a) => $a->currentStage() !== Role::Agencia)->count().' en el cliente';

            return new PageClock('ajuste', $state, $where, $total, $consumed, $remaining, $open->count(), $overdue, $frozen);
        }

        $stage = $this->status->approvalStage($page);
        $remaining = $this->approvalRemaining($page, $now);
        if ($stage === null || $remaining === null) {
            return null;
        }
        $sla = $this->sla()->approvalMinutes($stage);

        $verb = $this->status->phase($page) === PageStatus::PRINTING ? ($stage === Role::Marketing ? 'aprobar a imprenta' : 'aprobar') : 'validar';

        return new PageClock(
            'aprobacion', $this->tone($remaining, $sla), $stage->short().' debe '.$verb,
            $sla, min($sla, $sla - $remaining), $remaining, 0, $remaining < 0 ? 1 : 0, $frozen,
            $stage->short().': tiempo sugerido para '.$verb,
        );
    }
}
