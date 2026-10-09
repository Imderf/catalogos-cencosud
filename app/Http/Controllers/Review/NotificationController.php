<?php

namespace App\Http\Controllers\Review;

use App\Domain\Review\Inbox;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bandeja: what waits for the session's role, and what happened recently. */
class NotificationController extends Controller
{
    /** Tiny JSON for the browser's poll: how many unread and which one is the newest. */
    public function pulse(Request $request): JsonResponse
    {
        $user = $request->user();
        $n = $user->unreadNotifications()->latest()->first();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $n ? [
                'id' => $n->id,
                'text' => $n->data['text'] ?? '',
                'url' => $n->data['url'] ?? (! empty($n->data['page_number']) && ! empty($n->data['catalog_slug']) ? route('pages.show', [$n->data['catalog_slug'], $n->data['page_number']]) : null),
                'where' => $n->data['catalog_name'] ?? $n->data['project_name'] ?? null,
            ] : null,
        ])->header('Cache-Control', 'no-store');
    }

    public function index(Request $request, Inbox $inbox): Response
    {
        $user = $request->user();
        $pending = $inbox->items($user)->map(fn ($i) => [
            ...$i, 'remaining' => $i['remaining'] !== null ? round($i['remaining'], 2) : null,
            'url' => route('pages.show', [$i['catalog'], $i['page']]),
        ])->values();

        $recent = $user->notifications()->latest()->limit(40)->get()->map(fn ($n) => [
            'id' => $n->id,
            'kind' => $n->data['kind'] ?? 'ajuste',
            'text' => $n->data['text'] ?? '',
            'actor' => $n->data['actor'] ?? '',
            'catalog_name' => $n->data['catalog_name'] ?? $n->data['project_name'] ?? '',
            'page_number' => $n->data['page_number'] ?? null,
            'url' => $n->data['url'] ?? (isset($n->data['page_number']) && $n->data['page_number']
                ? route('pages.show', [$n->data['catalog_slug'], $n->data['page_number']])
                : route('catalogs.show', $n->data['catalog_slug'] ?? '')),
            'read' => $n->read_at !== null,
            'at' => $n->created_at->toIso8601String(),
        ]);

        $user->unreadNotifications()->update(['read_at' => now()]);

        $role = $user->isOgilvySide() ? 'Ogilvy' : ($user->role()?->label() ?? '');

        return Inertia::render('notifications/index', [
            'pending' => $pending,
            'recent' => $recent,
            'role_label' => $role,
            'is_agency' => $user->isOgilvySide(),
            'chain' => array_map(fn (Role $r) => $r->value, Role::chain()),
        ]);
    }
}
