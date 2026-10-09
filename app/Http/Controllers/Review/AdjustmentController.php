<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\CreateAdjustment;
use App\Actions\Review\DeleteAdjustment;
use App\Actions\Review\ReplyToAdjustment;
use App\Actions\Review\ResolveAdjustment;
use App\Actions\Review\UpdateAdjustment;
use App\Actions\Review\ValidateAdjustment;
use App\Domain\Review\AdjustmentRouter;
use App\Enums\AdjustmentType;
use App\Enums\Role;
use App\Http\Controllers\Concerns\ServesPrivateFiles;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Review\Concerns\HandlesDomainErrors;
use App\Models\Adjustment;
use App\Models\AdjustmentAttachment;
use App\Models\Catalog;
use App\Models\Page;
use App\Support\Box;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Enum;

class AdjustmentController extends Controller
{
    use HandlesDomainErrors, ServesPrivateFiles;

    public function store(Request $request, Catalog $catalog, Page $page, CreateAdjustment $create): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required_without:types', new Enum(AdjustmentType::class)],
            'types' => ['required_without:type', 'array', 'min:1'],
            'types.*' => [new Enum(AdjustmentType::class)],
            'text' => ['required', 'string', 'max:2000'],
            'box' => ['required', 'array'],
            'box.x' => ['required', 'numeric', 'between:0,1'],
            'box.y' => ['required', 'numeric', 'between:0,1'],
            'box.w' => ['required', 'numeric', 'between:0,1'],
            'box.h' => ['required', 'numeric', 'between:0,1'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,gif,webp', 'max:25600'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
        ], ['types.required_without' => 'Elige al menos un tipo de ajuste.', 'types.min' => 'Elige al menos un tipo de ajuste.', 'files.*.mimes' => 'Solo se aceptan PDF o imágenes (JPG, PNG, GIF, WebP).', 'files.*.max' => 'Cada archivo puede pesar hasta 25 MB.', 'files.max' => 'Máximo 5 archivos por ajuste.'], ['text' => 'descripción del ajuste', 'url' => 'enlace', 'files' => 'archivos']);

        $route = AdjustmentRouter::routeFrom($request->user()->role() ?? Role::Marketing);
        $to = $route[0] === Role::Agencia->value ? 'Ogilvy · cronómetro en marcha' : Role::from($route[0])->short().' para validación';

        return $this->attempt(
            fn () => $create->handle($request->user(), $page, $this->types($data), $data['text'], Box::fromArray($data['box']), files: $data['files'] ?? [], url: $data['url'] ?? null),
            'Ajuste enviado a '.$to
        );
    }

    /** One kind (`type`) or several (`types`). @return list<AdjustmentType> */
    private function types(array $data): array
    {
        return array_map(fn (string $v) => AdjustmentType::from($v), $data['types'] ?? [$data['type']]);
    }

    /** A client's attachment, for anyone who may see the page. */
    public function attachment(Request $request, Adjustment $adjustment, AdjustmentAttachment $attachment): Response
    {
        $this->authorize('view', $adjustment->page);
        abort_unless($attachment->adjustment_id === $adjustment->id && $attachment->isFile() && $attachment->path, 404);

        return $this->privateFile($attachment->path, $attachment->name, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function update(Request $request, Adjustment $adjustment, UpdateAdjustment $update): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required_without:types', new Enum(AdjustmentType::class)],
            'types' => ['required_without:type', 'array', 'min:1'],
            'types.*' => [new Enum(AdjustmentType::class)],
            'text' => ['required', 'string', 'max:2000'],
        ], ['types.required_without' => 'Elige al menos un tipo de ajuste.', 'types.min' => 'Elige al menos un tipo de ajuste.'], ['text' => 'descripción del ajuste']);

        return $this->attempt(fn () => $update->handle($request->user(), $adjustment, $this->types($data), $data['text']), 'Ajuste actualizado');
    }

    public function destroy(Request $request, Adjustment $adjustment, DeleteAdjustment $delete): RedirectResponse
    {
        return $this->attempt(fn () => $delete->handle($request->user(), $adjustment), 'Ajuste retirado');
    }

    public function validateStep(Request $request, Adjustment $adjustment, ValidateAdjustment $validate): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $next = $adjustment->route[1] ?? Role::Agencia->value;
        $msg = $next === Role::Agencia->value ? 'Ajuste validado y enviado a Ogilvy · cronómetro en marcha' : 'Ajuste enviado a '.Role::from($next)->short();

        return $this->attempt(fn () => $validate->handle($request->user(), $adjustment, $data['note'] ?? null), $msg);
    }

    public function resolve(Request $request, Adjustment $adjustment, ResolveAdjustment $resolve): RedirectResponse
    {
        return $this->attempt(fn () => $resolve->handle($request->user(), $adjustment), 'Ajuste resuelto · vuelve a la cadena de aprobación');
    }

    public function reply(Request $request, Adjustment $adjustment, ReplyToAdjustment $reply): RedirectResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']], [], ['text' => 'respuesta']);

        return $this->attempt(fn () => $reply->handle($request->user(), $adjustment, $data['text']), 'Respuesta enviada');
    }
}
