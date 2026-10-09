<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ManageCatalog;
use App\Actions\Review\SubmitPages;
use App\Domain\Projects\CatalogCards;
use App\Enums\CatalogKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Brand;
use App\Models\Card;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class CatalogManageController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly ReviewPresenter $presenter, private readonly ManageCatalog $manage) {}

    public function edit(Catalog $catalog): Response
    {
        $this->authorize('catalogs.manage');
        $catalog->load(['brand', 'pages.sectors', 'pages.versions', 'pages.adjustments', 'pages.approvals', 'pages.defects']);

        return Inertia::render('catalogs/edit', [
            'catalog' => [...$this->presenter->catalogSummary($catalog), 'brand_id' => $catalog->brand_id, 'project_id' => Card::where('catalog_id', $catalog->id)->value('project_id'), 'card_id' => Card::where('catalog_id', $catalog->id)->where('catalog_auto', false)->value('id')],
            'projects' => auth()->user()->can('projects.access')
                ? Project::visibleTo(auth()->user())->active()->with(['cards' => fn ($q) => $q->where(fn ($w) => $w->whereNull('catalog_id')->orWhere('catalog_id', $catalog->id))->withCount('subtasks')->orderBy('title')])->orderBy('name')->get(['id', 'name', 'color', 'client_id'])
                    ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'color' => $p->color, 'client_id' => $p->client_id, 'cards' => $p->cards->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'subtasks' => $c->subtasks_count])->values()])
                : [],
            'kinds' => CatalogKind::options(),
            'pages' => $catalog->pages->map(fn (Page $p) => [
                'id' => $p->id, 'number' => $p->number, 'label' => $p->label(), 'version' => $p->current_version,
                'sector_ids' => $p->sectors->pluck('id')->values(), 'thumb_url' => $p->currentVersion()?->thumbUrl(), 'submitted' => $p->isSubmitted(),
                'open' => $p->adjustments->where('status', 'open')->count(),
            ])->values(),
            'brands' => Brand::orderBy('name')->get(['id', 'name', 'client_id']),
            'sectors' => Sector::orderBy('position')->get(['id', 'slug', 'name', 'abbr', 'group']),
        ]);
    }

    public function update(Request $request, Catalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'brand_id' => ['required', Rule::exists('brands', 'id')],
            'kind' => ['required', new Enum(CatalogKind::class)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')],
            'card_id' => ['nullable', Rule::exists('cards', 'id')],
        ], [], ['name' => 'nombre', 'brand_id' => 'marca', 'kind' => 'tipo', 'project_id' => 'proyecto', 'card_id' => 'tarea']);

        return $this->attempt(function () use ($request, $catalog, $data) {
            $this->manage->update($request->user(), $catalog, $data['name'], Brand::findOrFail($data['brand_id']), CatalogKind::from($data['kind']));
            if ($request->user()->can('projects.access')) {
                $project = ! empty($data['project_id']) ? Project::visibleTo($request->user())->active()->find($data['project_id']) : null;
                $card = $project && ! empty($data['card_id']) ? $project->cards()->whereKey($data['card_id'])->first() : null;
                app(CatalogCards::class)->link($catalog->fresh(), $project, $request->user(), $card);
            }
        }, 'Catálogo actualizado.');
    }

    public function addPages(Request $request, Catalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'pages' => ['required', 'array', 'min:1', 'max:64'],
            'pages.*.image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'pages.*.sectors' => ['required', 'array', 'min:1'],
            'pages.*.sectors.*' => ['integer', Rule::exists('sectors', 'id')],
        ], ['pages.*.sectors.required' => 'Toda página necesita al menos un sector.'], ['pages' => 'páginas']);
        $n = count($data['pages']);

        return $this->attempt(
            fn () => $this->manage->addPages($request->user(), $catalog, array_map(fn ($p) => ['image' => $p['image'], 'sectors' => array_map('intval', $p['sectors'])], $data['pages'])),
            $n.' página'.($n === 1 ? '' : 's').' agregada'.($n === 1 ? '' : 's').' · la revisión empieza en Marketing.'
        );
    }

    public function reorder(Request $request, Catalog $catalog): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        return $this->attempt(fn () => $this->manage->reorder($request->user(), $catalog, array_map('intval', $data['order'])), 'Orden guardado.');
    }

    public function pageSectors(Request $request, Catalog $catalog, Page $page): RedirectResponse
    {
        $data = $request->validate(['sectors' => ['required', 'array', 'min:1'], 'sectors.*' => ['integer', Rule::exists('sectors', 'id')]], ['sectors.required' => 'Toda página necesita al menos un sector.']);

        return $this->attempt(fn () => $this->manage->setSectors($request->user(), $page, array_map('intval', $data['sectors'])), $page->label().' · sectores guardados.');
    }

    /** Sends every page still in preparation, or just the given one. */
    public function submit(Request $request, Catalog $catalog, SubmitPages $submit, ?Page $page = null): RedirectResponse
    {
        $ids = $page ? [$page->id] : null;

        return $this->attempt(function () use ($request, $catalog, $submit, $ids) {
            $sent = $submit->handle($request->user(), $catalog, $ids);
            session()->flash('success', $sent->count() === 1 ? $sent->first()->label().' enviada a Marketing · empieza la validación.' : $sent->count().' páginas enviadas a Marketing · empieza la validación.');
        }, '');
    }

    public function archive(Request $request, Catalog $catalog): RedirectResponse
    {
        return $this->attempt(fn () => $this->manage->archive($request->user(), $catalog), 'Catálogo archivado. Ya no aparece en la lista ni en las bandejas.');
    }

    public function destroy(Request $request, Catalog $catalog): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'string']]);
        if (trim($request->string('confirm')) !== $catalog->name) {
            return back()->with('error', 'El nombre escrito no coincide; el catálogo no se eliminó.');
        }
        try {
            $this->manage->delete($request->user(), $catalog);
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('catalogs.index')->with('success', 'Catálogo "'.$catalog->name.'" eliminado.');
    }

    public function unarchive(Request $request, Catalog $catalog): RedirectResponse
    {
        return $this->attempt(fn () => $this->manage->unarchive($request->user(), $catalog), 'Catálogo reactivado.');
    }
}
