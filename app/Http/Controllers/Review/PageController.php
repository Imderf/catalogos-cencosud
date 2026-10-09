<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ApproveForPrint;
use App\Actions\Review\ReopenAsDefect;
use App\Actions\Review\UploadVersion;
use App\Actions\Review\ValidatePage;
use App\Domain\Review\Clocks;
use App\Enums\AdjustmentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Catalog;
use App\Models\Page;
use App\Support\Box;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(private readonly ReviewPresenter $presenter) {}

    public function show(Request $request, Catalog $catalog, Page $page): Response
    {
        $catalog->load(['brand', 'pages.sectors', 'pages.versions', 'pages.adjustments', 'pages.approvals', 'pages.defects']);
        $page->load(['sectors', 'versions', 'adjustments.author', 'adjustments.attachments', 'adjustments.validations.user', 'adjustments.replies.user', 'approvals.user', 'defects.user']);
        $user = $request->user();
        $numbers = $catalog->pages->pluck('number')->values();
        $i = $numbers->search($page->number);

        return Inertia::render('pages/show', [
            'catalog' => $this->presenter->catalogSummary($catalog),
            'page' => $this->presenter->pageDetail($page, $user, $request->integer('version') ?: null),
            'nav' => ['prev' => $numbers[$i - 1] ?? null, 'next' => $numbers[$i + 1] ?? null, 'count' => $numbers->count()],
            'types' => AdjustmentType::options(),
            'sla' => app(Clocks::class)->sla()->toArray(),
        ]);
    }

    public function validate(Request $request, Catalog $catalog, Page $page, ValidatePage $validate): RedirectResponse
    {
        return $this->attempt(fn () => $validate->handle($request->user(), $page), $page->label().' validada.');
    }

    public function approvePrint(Request $request, Catalog $catalog, Page $page, ApproveForPrint $approve): RedirectResponse
    {
        return $this->attempt(fn () => $approve->handle($request->user(), $page), $page->label().' aprobada a imprenta.');
    }

    public function defect(Request $request, Catalog $catalog, Page $page, ReopenAsDefect $reopen): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', new Enum(AdjustmentType::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'box' => ['nullable', 'array'],
        ], [], ['reason' => 'descripción del defecto']);

        return $this->attempt(
            fn () => $reopen->handle($request->user(), $page, AdjustmentType::from($data['type']), $data['reason'], isset($data['box']) ? Box::fromArray($data['box']) : null),
            $page->label().' reabierta como defecto post-producción.'
        );
    }

    public function storeVersion(Request $request, Catalog $catalog, Page $page, UploadVersion $upload): RedirectResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'resolved' => ['nullable', 'array'],
            'resolved.*' => ['integer'],
        ], [], ['image' => 'imagen']);

        return $this->attempt(
            fn () => $upload->handle($request->user(), $page, $data['image'], array_map('intval', $data['resolved'] ?? [])),
            'Versión v'.($page->current_version + 1).' cargada · vuelve a revisión en Marketing.'
        );
    }
}
