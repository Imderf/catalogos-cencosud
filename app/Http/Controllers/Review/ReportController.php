<?php

namespace App\Http\Controllers\Review;

use App\Domain\Review\PageStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Adjustment;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReviewPresenter $presenter, private readonly PageStatus $status) {}

    public function show(Request $request, Catalog $catalog): Response
    {
        $catalog->load(['brand', 'pages.sectors', 'pages.versions', 'pages.adjustments.author', 'pages.adjustments.validations.user', 'pages.approvals.user', 'pages.defects.user']);
        $totals = $this->presenter->totals($catalog);
        $pages = $catalog->pages;

        $bySector = [];
        foreach (Sector::orderBy('position')->get() as $s) {
            $n = $pages->filter(fn (Page $p) => $p->sectors->first()?->slug === $s->slug)->sum(fn (Page $p) => $p->adjustments->count());
            if ($pages->contains(fn (Page $p) => $p->sectors->first()?->slug === $s->slug)) {
                $bySector[] = ['sector' => $s->name, 'value' => $n];
            }
        }

        $log = collect();
        foreach ($pages as $p) {
            $v1 = $p->versions->firstWhere('number', 1);
            $log->push(['ts' => $v1?->created_at, 'kind' => 'carga', 'page' => $p->number, 'text' => 'cargada (v1, '.$p->sectors->pluck('name')->join(' · ').')', 'by' => $v1?->uploader?->name ?? 'Ogilvy']);
            foreach ($p->adjustments as $a) {
                $log->push(['ts' => $a->created_at, 'kind' => 'ajuste', 'page' => $p->number, 'text' => ($a->is_defect ? 'defecto post-producción' : 'ajuste').' de '.$a->typeShort().' creado en v'.$a->version.' por '.$a->origin_role->short(), 'by' => $a->author?->name ?? '—']);
                foreach ($a->validations as $x) {
                    $log->push(['ts' => $x->created_at, 'kind' => 'validacion', 'page' => $p->number, 'text' => 'ajuste validado por '.$x->role->short(), 'by' => $x->user?->name ?? '—']);
                }
            }
            foreach ($p->versions->where('number', '>', 1) as $v) {
                $res = $p->adjustments->where('resolved_in_version', $v->number)->count();
                $log->push(['ts' => $v->created_at, 'kind' => 'version', 'page' => $p->number, 'text' => 'nueva versión v'.$v->number.' ('.$res.' ajuste(s) resuelto(s))', 'by' => $v->uploader?->name ?? 'Ogilvy']);
            }
            foreach ($p->approvals as $a) {
                $log->push(['ts' => $a->created_at, 'kind' => 'aprobacion', 'page' => $p->number, 'text' => (($a->phase ?? 'validation') === 'print' ? 'aprobada a imprenta por ' : 'validada por ').$a->role->short().' en v'.$a->version, 'by' => $a->user?->name ?? '—']);
            }
            foreach ($p->defects as $d) {
                $log->push(['ts' => $d->created_at, 'kind' => 'defecto', 'page' => $p->number, 'text' => 'reabierta por defecto post-producción sobre v'.$d->version, 'by' => $d->user?->name ?? '—']);
            }
        }

        return Inertia::render('catalogs/report', [
            'catalog' => $this->presenter->catalogSummary($catalog),
            'kpis' => [
                ...$totals,
                'pages' => $pages->count(),
                'multi_version' => $pages->where('current_version', '>', 1)->count(),
                'average' => $pages->count() ? round($totals['total'] / $pages->count(), 1) : 0,
            ],
            'by_sector' => $bySector,
            'rows' => $pages->map(fn (Page $p) => [
                'number' => $p->number, 'sectors' => $p->sectors->pluck('name')->join(' · '), 'version' => $p->current_version,
                'total' => $p->adjustments->count(), 'open' => $p->adjustments->filter(fn (Adjustment $a) => $a->isOpen())->count(),
                'state' => $this->status->isApproved($p) ? 'Cerrada' : $this->status->location($p)->label,
            ])->sortByDesc('total')->values(),
            'log' => $log->sortByDesc('ts')->values()->map(fn ($l) => [...$l, 'ts' => $l['ts']?->toIso8601String()]),
        ]);
    }

    public function csv(Catalog $catalog): StreamedResponse
    {
        $catalog->load(['pages.sectors', 'pages.adjustments', 'pages.approvals', 'pages.defects']);
        $rows = [['Catalogo', 'Pagina', 'Categoria', 'Ajustes', 'Abiertos', 'Resueltos', 'Defectos post-produccion', 'Version', 'Etapa', 'Aprobada']];
        foreach ($catalog->pages as $p) {
            $approved = $this->status->isApproved($p);
            $rows[] = [
                $catalog->name, $p->number, $p->sectors->pluck('name')->join(' · '), $p->adjustments->count(),
                $p->adjustments->filter(fn (Adjustment $a) => $a->isOpen())->count(), $p->adjustments->filter(fn (Adjustment $a) => ! $a->isOpen())->count(),
                $p->defects->count(), 'v'.$p->current_version, $approved ? 'Cerrada' : $this->status->location($p)->label, $approved ? 'Si' : 'No',
            ];
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'reporte-'.$catalog->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
