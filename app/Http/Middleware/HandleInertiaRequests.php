<?php

namespace App\Http\Middleware;

use App\Domain\Review\Inbox;
use App\Domain\Review\PageStatus;
use App\Http\Presenters\ReviewPresenter;
use App\Models\Page;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'organization' => $user->organization,
                    'role' => $user->role()?->value,
                    'role_label' => $user->role()?->label(),
                    'sectors' => $user->sectors->map(fn (Sector $s) => ['id' => $s->id, 'slug' => $s->slug, 'name' => $s->name, 'abbr' => $s->abbr])->values(),
                    'must_change_password' => $user->must_change_password,
                    'notification_mode' => $user->notification_mode,
                    'notification_sound' => (bool) $user->notification_sound,
                    'acts_as_agency' => $user->actsAsAgency(),
                    'read_only' => $user->isReadOnly(),
                    'can' => [
                        'manage_users' => $user->can('users.manage'),
                        'manage_catalogs' => $user->can('catalogs.manage'),
                        'manage_sla' => $user->can('sla.manage'),
                        'projects' => $user->can('projects.access'),
                        'manage_clients' => $user->can('clients.manage'),
                    ],
                ] : null,
            ],
            'workday' => fn () => $user ? app(ReviewPresenter::class)->workday() : null,
            'inbox' => fn () => $user ? app(Inbox::class)->counts($user) : null,
            'unread' => fn () => $user ? $user->unreadNotifications()->count() : 0,
            'round' => fn () => $user ? $this->round() : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                /* Changes on every response that carries a message, so toasts fire even when the text repeats. */
                'key' => fn () => $request->session()->has('success') || $request->session()->has('error') ? (string) Str::uuid() : null,
            ],
        ];
    }

    /** Pages approved by the whole chain across every open catalog, for the sidebar. */
    private function round(): array
    {
        $status = app(PageStatus::class);
        $pages = Page::with('approvals')->whereHas('catalog', fn ($q) => $q->active())->get();

        return [
            'approved' => $pages->filter(fn (Page $p) => $status->isApproved($p))->count(),
            'pages' => $pages->count(),
        ];
    }
}
