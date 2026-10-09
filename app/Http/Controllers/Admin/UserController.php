<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\AccountCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['roles', 'sectors'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$q}%")
                ->orWhere('email', 'ilike', "%{$q}%")
                ->orWhere('organization', 'ilike', "%{$q}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->present($u));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['q' => $q],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/create', $this->formProps());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'organization' => $data['organization'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);
        $this->syncRoleAndSectors($user, $data);

        $user->notify(new AccountCreated($request->user()->name));

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario {$user->name} creado. Entrégale la contraseña en persona; deberá cambiarla al ingresar.");
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('admin/users/edit', [
            ...$this->formProps(),
            'user' => $this->present($usuario->load(['roles', 'sectors'])),
        ]);
    }

    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $data = $request->validated();

        $usuario->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'organization' => $data['organization'] ?? null,
            'is_active' => $data['is_active'] ?? $usuario->is_active,
        ]);

        if (! empty($data['password'])) {
            $usuario->password = $data['password'];
            $usuario->must_change_password = true;
        }

        if ($usuario->is($request->user())) {
            // An administrator can never lock themselves out.
            $usuario->is_active = true;
            $data['role'] = Role::Admin->value;
        }

        $usuario->save();
        $this->syncRoleAndSectors($usuario, $data);

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario {$usuario->name} actualizado.");
    }

    /** Deactivate instead of delete: history must keep pointing at real people. */
    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $usuario->update(['is_active' => false]);

        return back()->with('success', "Usuario {$usuario->name} desactivado.");
    }

    private function syncRoleAndSectors(User $user, array $data): void
    {
        $role = Role::from($data['role']);
        $user->syncRoles([$role->value]);
        $user->sectors()->sync($role->usesSectors() ? ($data['sectors'] ?? []) : []);
    }

    private function formProps(): array
    {
        return [
            'roles' => Role::options(),
            'sectors' => Sector::orderBy('position')->get(['id', 'slug', 'name', 'abbr', 'group']),
        ];
    }

    private function present(User $u): array
    {
        $role = $u->role();

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'organization' => $u->organization,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'side' => $u->isOgilvySide() ? 'Ogilvy' : 'Cencosud',
            'sectors' => $u->sectors->map(fn (Sector $s) => ['id' => $s->id, 'slug' => $s->slug, 'name' => $s->name, 'abbr' => $s->abbr])->values(),
            'is_active' => $u->is_active,
            'must_change_password' => $u->must_change_password,
            'created_at' => $u->created_at?->toIso8601String(),
        ];
    }
}
