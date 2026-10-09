<?php

namespace App\Events\Review;

use App\Models\Adjustment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event; listeners (notifications, activity) are wired in phase 4. */
class AdjustmentReplied
{
    use Dispatchable;

    public function __construct(public Adjustment $adjustment, public User $by) {}
}
