<?php

namespace App\Domain\Review;

use App\Enums\Role;
use App\Models\Adjustment;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Collection;

/** What waits for the session's role right now, ordered by urgency. */
class Inbox
{
    public function __construct(private readonly PageStatus $status, private readonly Clocks $clocks) {}

    /** @return array{approvals:int, adjustments:int, total:int} */
    public function counts(User $user): array
    {
        $items = $this->items($user);
        $approvals = $items->where('kind', 'approval')->count();
        $adjustments = $items->where('kind', 'adjustment')->count();

        return ['approvals' => $approvals, 'adjustments' => $adjustments, 'total' => $approvals + $adjustments];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function items(User $user): Collection
    {
        $role = $user->role();
        if ($role === null) {
            return collect();
        }
        $acting = $user->actsAsAgency() ? Role::Agencia : $role;
        $out = collect();

        Catalog::active()->with(['pages.sectors', 'pages.adjustments', 'pages.approvals'])->get()->each(function (Catalog $catalog) use (&$out, $user, $acting) {
            $catalog->pages->each(function (Page $page) use (&$out, $user, $acting, $catalog) {
                if (! $user->canActOn($page)) {
                    return;
                }
                $page->adjustments->each(function (Adjustment $a) use (&$out, $acting, $catalog, $page) {
                    if ($a->currentStage() !== $acting) {
                        return;
                    }
                    $rest = $this->clocks->adjustmentRemaining($a);
                    $out->push([
                        'kind' => 'adjustment', 'order' => $rest ?? 1e9,
                        'title' => ($acting === Role::Agencia ? 'Ejecutar ajuste de ' : 'Validar ajuste de ').$a->type->short(),
                        'detail' => $catalog->name.' · '.$page->label(),
                        'remaining' => $rest, 'state' => $this->clocks->adjustmentState($a),
                        'catalog' => $catalog->slug, 'page' => $page->number,
                    ]);
                });
                if ($acting !== Role::Agencia && $this->status->approvalStage($page) === $acting && ! $this->status->hasOpenAdjustments($page)) {
                    $rest = $this->clocks->approvalRemaining($page);
                    $out->push([
                        'kind' => 'approval', 'order' => $rest ?? 1e9,
                        'title' => $this->status->phase($page) === PageStatus::PRINTING ? ($acting === Role::Marketing ? 'Aprobar a imprenta' : 'Aprobar página') : 'Validar página',
                        'detail' => $catalog->name.' · '.$page->label().' · '.$page->sectors->pluck('name')->join(' · '),
                        'remaining' => $rest, 'state' => $this->clocks->tone($rest, $this->clocks->sla()->approvalMinutes($acting)),
                        'catalog' => $catalog->slug, 'page' => $page->number,
                    ]);
                }
            });
        });

        return $out->sortBy('order')->values();
    }
}
