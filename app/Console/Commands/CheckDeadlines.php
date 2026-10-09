<?php

namespace App\Console\Commands;

use App\Domain\Review\AgencyQueue;
use App\Domain\Review\Alerts;
use App\Domain\Review\Clocks;
use App\Domain\Review\PageStatus;
use App\Domain\Time\BusinessTime;
use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Page;
use App\Notifications\Review\AlertPayload;
use Illuminate\Console\Command;

/**
 * Runs every minute during business hours: warns the role holding the ball when a
 * deadline enters its last 25 % and again when it expires. Each level fires once per stage.
 */
class CheckDeadlines extends Command
{
    protected $signature = 'review:check-deadlines';

    protected $description = 'Avisa plazos urgentes y vencidos de ajustes y aprobaciones';

    public function handle(BusinessTime $time, Clocks $clocks, PageStatus $status, Alerts $alerts): int
    {
        if (! $time->isOpen()) {
            return self::SUCCESS;
        }
        $sent = 0;
        app(AgencyQueue::class)->refresh();

        Adjustment::open()->whereHas('page.catalog', fn ($q) => $q->active())->with('page.catalog', 'page.sectors')->get()->each(function (Adjustment $a) use ($clocks, $alerts, &$sent) {
            $level = $this->level($clocks->adjustmentState($a));
            if ($level === null || $a->alert_level === $level || ($a->alert_level === 'vencido')) {
                return;
            }
            $page = $a->page;
            $stage = $a->currentStage();
            $who = $stage === Role::Agencia ? 'Ogilvy' : $stage->short();
            $text = $level === 'vencido'
                ? 'Venció el plazo del ajuste de '.$a->type->short().' en la '.$page->label().' · en '.$who
                : 'Queda menos del 25 % del plazo del ajuste de '.$a->type->short().' en la '.$page->label();
            $roles = $level === 'vencido' && $stage !== Role::Agencia ? [$stage, Role::Agencia] : [$stage];
            $alerts->toRoles($roles, AlertPayload::about($level, $text, 'Sistema', $page->catalog, $page), $page);
            $a->forceFill(['alert_level' => $level])->save();
            $sent++;
        });

        Page::whereHas('catalog', fn ($q) => $q->active())->with('catalog', 'sectors', 'adjustments', 'approvals')->get()->each(function (Page $page) use ($clocks, $status, $alerts, &$sent) {
            $remaining = $clocks->approvalRemaining($page);
            $stage = $status->approvalStage($page);
            if ($remaining === null || $stage === null) {
                return;
            }
            $level = $this->level($clocks->tone($remaining, $clocks->sla()->approvalMinutes($stage)));
            if ($level === null || $page->approval_alert_level === $level || $page->approval_alert_level === 'vencido') {
                return;
            }
            $verb = $status->phase($page) === PageStatus::PRINTING ? 'apruebe a imprenta' : 'valide';
            $text = $level === 'vencido'
                ? 'Venció el plazo de '.$stage->short().' para que '.$verb.' la '.$page->label().' de '.$page->catalog->name
                : 'Queda menos del 25 % del plazo para que '.$stage->short().' '.$verb.' la '.$page->label();
            $roles = $level === 'vencido' ? [$stage, Role::Agencia] : [$stage];
            $alerts->toRoles($roles, AlertPayload::about($level, $text, 'Sistema', $page->catalog, $page), $page);
            $page->forceFill(['approval_alert_level' => $level])->save();
            $sent++;
        });

        $this->info("Alertas enviadas: {$sent}");

        return self::SUCCESS;
    }

    private function level(string $state): ?string
    {
        return match ($state) {
            'vencido' => 'vencido',
            'urgente' => 'urgente',
            default => null,
        };
    }
}
