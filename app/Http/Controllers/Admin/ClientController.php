<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ServesPrivateFiles;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Client directory, a tab inside Configuración. Brands (Jumbo, Metro) hang from a client. */
class ClientController extends Controller
{
    use ServesPrivateFiles;

    public function index(): Response
    {
        return Inertia::render('admin/clients/index', [
            'clients' => Client::withCount(['projects', 'brands'])->with('brands:id,client_id,name')->orderBy('name')->get()
                ->map(fn (Client $c) => [
                    'id' => $c->id, 'name' => $c->name, 'color' => $c->color, 'is_active' => $c->is_active, 'logo_url' => $c->logoUrl(),
                    'projects_count' => $c->projects_count, 'brands' => $c->brands->pluck('name')->values(),
                ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create($this->validated($request));

        return back()->with('success', 'Cliente creado.');
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));

        return back()->with('success', 'Cliente actualizado.');
    }

    public function logo(Request $request, Client $client): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096']], [], ['logo' => 'logo']);
        $file = $request->file('logo');
        if ($client->logo_path) {
            Storage::disk('local')->delete($client->logo_path);
        }
        $client->update(['logo_path' => $file->storeAs('clientes', $client->id.'-'.Str::random(8).'.'.$file->getClientOriginalExtension(), 'local')]);

        return back()->with('success', 'Logo actualizado.');
    }

    public function destroyLogo(Client $client): RedirectResponse
    {
        if ($client->logo_path) {
            Storage::disk('local')->delete($client->logo_path);
            $client->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logo eliminado.');
    }

    /** Any signed-in user may see a client's logo (it shows on boards and catalogs). */
    public function showLogo(Client $client): HttpResponse
    {
        abort_unless($client->logo_path && Storage::disk('local')->exists($client->logo_path), 404);

        return $this->privateFile($client->logo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('clients', 'name')->ignore($client)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], ['name' => 'nombre']);
    }
}
