<?php

namespace App\Events\Review;

use App\Models\Defect;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event; listeners (notifications, activity) are wired in phase 4. */
class PageReopenedAsDefect
{
    use Dispatchable;

    public function __construct(public Defect $defect, public User $by) {}
}
