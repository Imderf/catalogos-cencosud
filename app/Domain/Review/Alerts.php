<?php

namespace App\Domain\Review;

use App\Enums\Role;
use App\Models\Page;
use App\Models\User;
use App\Notifications\Review\AlertPayload;
use App\Notifications\Review\ReviewAlert;
use App\Notifications\Review\ReviewAlertMail;
use Illuminate\Support\Collection;

/** Fan-out of one alert to every recipient, in the app and by email. */
class Alerts
{
    public function __construct(private readonly Recipients $recipients) {}

    /** @param  list<Role>  $roles */
    public function toRoles(array $roles, AlertPayload $payload, ?Page $page = null, ?User $except = null): void
    {
        $this->send($this->recipients->forRoles($roles, $page), $payload, $except);
    }

    /** @param  Collection<int, User>  $users */
    public function send(Collection $users, AlertPayload $payload, ?User $except = null): void
    {
        $users->reject(fn (User $u) => $except && $u->is($except))->each(function (User $u) use ($payload) {
            $u->notify(new ReviewAlert($payload));
            if (! $u->wantsDailyDigest()) {
                $u->notify(new ReviewAlertMail($payload));
            }
        });
    }
}
