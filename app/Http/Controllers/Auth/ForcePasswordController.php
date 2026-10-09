<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** First-login password change, required for accounts created by an administrator. */
class ForcePasswordController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('catalogs.index');
        }

        return Inertia::render('auth/force-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'different:current_password', Password::defaults(), 'confirmed'],
        ], [], ['current_password' => 'contraseña actual', 'password' => 'nueva contraseña']);

        $request->user()->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('catalogs.index')->with('success', 'Contraseña actualizada. Bienvenido.');
    }
}
