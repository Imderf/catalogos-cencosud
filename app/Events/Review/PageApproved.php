<?php

namespace App\Events\Review;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event; listeners (notifications, activity) are wired in phase 4. */
class PageApproved
{
    use Dispatchable;

    public function __construct(public Approval $approval, public User $by) {}
}
