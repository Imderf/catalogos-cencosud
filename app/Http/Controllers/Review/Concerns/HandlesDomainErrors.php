<?php

namespace App\Http\Controllers\Review\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

/** Domain refusals come back to the same screen as a flash error, never as a 403 page. */
trait HandlesDomainErrors
{
    protected function attempt(callable $fn, string $success): RedirectResponse
    {
        try {
            $fn();
        } catch (AuthorizationException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $success === '' ? back() : back()->with('success', $success);
    }
}
