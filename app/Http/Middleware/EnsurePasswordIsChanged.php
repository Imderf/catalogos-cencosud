<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Users flagged with must_change_password can only reach the change-password screen and logout. */
class EnsurePasswordIsChanged
{
    private const ALLOWED = ['password.force.edit', 'password.force.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return redirect()->route('password.force.edit');
        }

        return $next($request);
    }
}
