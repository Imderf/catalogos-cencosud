<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\PublishCatalog;
use App\Domain\Projects\CatalogCards;
use App\Enums\AdjustmentType;
use App\Enums\CatalogKind;
use App\Http\Controllers\Controller;
use App\Http\Presenters\ReviewPresenter;
use App\Http\Requests\Review\StoreCatalogRequest;
use App\Models\Brand;
use App\Models\Catalog;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private readonly ReviewPresenter $presenter) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $archived = $request->boolean('archivados');
        $kind = CatalogKind::tryFrom((string) $request->query('tipo', ''));
        $catalogs = Catalog::with(['brand', 'pages.adjustments', 'pages.approvals', 'pages.defects'])
            ->when($q !== '', fn ($query) => $query->where('name', 'ilike', "%{$q}%"))
            ->when($archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->active())
            ->when($kind, fn ($query) => $query->where('kind', $kind->value))
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Catalog $c) => $this->presenter->catalogSummary($c));

        return Inertia::render('catalogs/index', ['catalogs' => $catalogs, 'filters' => ['q' => $q, 'archivados' => $archived, 'tipo' => $kind?->value], 'kinds' => CatalogKind::options()]);
    }

    public function create(): Response
    {
        $this->authorize('catalogs.manage');

        return Inertia::render('catalogs/create', [
            'brands' => Brand::orderBy('name')->get(['id', 'slug', 'name', 'client_id']),
            'sectors' => Sector::orderBy('position')->get(['id', 'slug', 'name', 'abbr', 'group']),
            'kinds' => CatalogKind::options(),
            'projects' => $this->projectsFor(auth()->user()),
        ]);
    }

    public function store(StoreCatalogRequest $request, PublishCatalog $publish): RedirectResponse
    {
        $data = $request->validated();
        $catalog = $publish->handle(
            $request->user(),
            Brand::findOrFail($data['brand_id']),
            $data['name'],
            array_map(fn ($p) => ['image' => $p['image'], 'sectors' => array_map('intval', $p['sectors'])], $data['pages']),
            CatalogKind::from($data['kind']),
        );
        if (! empty($data['project_id'])) {
            $project = Project::visibleTo($request->user())->active()->find($data['project_id']);
            $card = $project && ! empty($data['card_id']) ? $project->cards()->whereKey($data['card_id'])->first() : null;
            try {
                app(CatalogCards::class)->link($catalog, $project, $request->user(), $card);
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('catalogs.show', $catalog)->with('error', 'Catálogo cargado, pero no se ligó al proyecto: '.$e->getMessage());
            }
        }

        return redirect()->route('catalogs.show', $catalog)->with('success', 'Catálogo cargado. Las páginas quedan en preparación: envíalas a Marketing cuando estén listas.');
    }

    public function show(Request $request, Catalog $catalog): Response
    {
        $catalog->load(['brand', 'pages.sectors', 'pages.versions', 'pages.adjustments', 'pages.approvals', 'pages.defects']);

        return Inertia::render('catalogs/show', [
            'catalog' => $this->presenter->catalogSummary($catalog),
            'pages' => $catalog->pages->map(fn ($p) => $this->presenter->pageCard($p, $request->user()))->values(),
            'types' => AdjustmentType::options(),
        ]);
    }

    /** Boards the user may put a catalog on; empty for people without projects. */
    private function projectsFor($user): array
    {
        if (! $user->can('projects.access')) {
            return [];
        }

        /* With the brand's client first, the board most likely to be right sits at the top. */
        return Project::visibleTo($user)->active()->withCount(['cards as catalogs_count' => fn ($q) => $q->whereNotNull('catalog_id')])
            ->with(['cards' => fn ($q) => $q->whereNull('catalog_id')->withCount('subtasks')->orderBy('title')])
            ->orderByDesc('catalogs_count')->orderBy('name')->get(['id', 'name', 'color', 'client_id'])
            ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'color' => $p->color, 'client_id' => $p->client_id,
                'cards' => $p->cards->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'subtasks' => $c->subtasks_count])->values()])->all();
    }
}
