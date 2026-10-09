<?php

namespace App\Http\Controllers\Review;

use App\Domain\Review\Clocks;
use App\Domain\Review\PageStatus;
use App\Domain\Time\BusinessTime;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Adjustment;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The monthly conversation with Cencosud: every catalog published in the month, on one page. */
class MonthlyReportController extends Controller
{
    public function __construct(
        private readonly ReviewPresenter $presenter,
        private readonly PageStatus $status,
        private readonly Clocks $clocks,
        private readonly BusinessTime $time,
    ) {}

    public function show(Request $request): Response
    {
        $month = $this->month($request);
        $data = $this->build($month);

        return Inertia::render('reports/monthly', [
            ...$data,
            'month' => $month->format('Y-m'),
            'month_label' => $month->locale('es')->isoFormat('MMMM YYYY'),
            'prev' => $month->subMonth()->format('Y-m'),
            'next' => $month->addMonth()->format('Y-m'),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $data = $this->build($month);
        $rows = [['Catalogo', 'Marca', 'Publicado', 'Paginas', 'Aprobadas', 'Ajustes', 'Abiertos', 'Defectos post-produccion', 'Ajustes fuera de tiempo', 'Aprobaciones vencidas', 'Ejecucion media Ogilvy (min)', 'Demora media del cliente (min)', 'Estado']];
        foreach ($data['rows'] as $r) {
            $rows[] = [$r['name'], $r['brand'], $r['published_at'], $r['pages'], $r['approved'], $r['total'], $r['open'], $r['defects'], $r['overdue'], $r['approvalsOverdue'], $r['avg_execution'], $r['avg_delay'], $r['state']];
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'reporte-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function month(Request $request): CarbonImmutable
    {
        $raw = (string) $request->query('mes', '');

        return preg_match('/^\d{4}-\d{2}$/', $raw)
            ? CarbonImmutable::createFromFormat('Y-m-d', $raw.'-01', $this->time->hours()->timezone)->startOfMonth()
            : $this->time->now()->startOfMonth();
    }

    private function build(CarbonImmutable $month): array
    {
        $catalogs = Catalog::with(['brand', 'pages.sectors', 'pages.adjustments', 'pages.approvals', 'pages.defects'])
            ->whereBetween('published_at', [$month, $month->endOfMonth()])
            ->orderBy('published_at')
            ->get();

        $rows = $catalogs->map(function (Catalog $c) {
            $t = $this->presenter->totals($c);
            $executed = $c->pages->flatMap->adjustments->filter(fn (Adjustment $a) => ! $a->isOpen() && $a->received_by_agency_at && $a->resolved_at);
            $approvals = $c->pages->flatMap->approvals;

            return [
                'slug' => $c->slug, 'name' => $c->name, 'brand' => $c->brand->name, 'kind' => $c->kind?->label() ?? 'Octavilla',
                'published_at' => $c->published_at->format('Y-m-d'),
                'pages' => $c->pages->count(), ...$t,
                'avg_execution' => $executed->isEmpty() ? null : (int) round($executed->avg(fn (Adjustment $a) => $this->time->workingMinutesBetween($a->agency_started_at ?? $a->received_by_agency_at, $a->resolved_at))),
                'avg_delay' => $approvals->isEmpty() ? null : (int) round($approvals->avg('delay_minutes')),
                'state' => $c->isArchived() ? 'Archivado' : ($t['approved'] === $c->pages->count() && $c->pages->isNotEmpty() ? 'Cerrado' : 'En revisión'),
            ];
        })->values();

        $sum = fn (string $k) => $rows->sum($k);
        $pages = $sum('pages');
        $bySector = Sector::orderBy('position')->get()->map(fn (Sector $s) => [
            'sector' => $s->name,
            'value' => $catalogs->flatMap->pages->filter(fn (Page $p) => $p->sectors->first()?->slug === $s->slug)->sum(fn (Page $p) => $p->adjustments->count()),
        ])->filter(fn ($b) => $b['value'] > 0)->values();
        $executed = $rows->whereNotNull('avg_execution');
        $delayed = $rows->whereNotNull('avg_delay');

        return [
            'kpis' => [
                'catalogs' => $rows->count(), 'pages' => $pages, 'approved' => $sum('approved'),
                'total' => $sum('total'), 'open' => $sum('open'), 'defects' => $sum('defects'),
                'overdue' => $sum('overdue'), 'approvalsOverdue' => $sum('approvalsOverdue'),
                'avg_execution' => $executed->isEmpty() ? null : (int) round($executed->avg('avg_execution')),
                'avg_delay' => $delayed->isEmpty() ? null : (int) round($delayed->avg('avg_delay')),
                'average' => $pages ? round($sum('total') / $pages, 1) : 0,
            ],
            'rows' => $rows,
            'by_sector' => $bySector,
        ];
    }
}
