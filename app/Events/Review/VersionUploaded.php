<?php

namespace App\Events\Review;

use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event; listeners (notifications, activity) are wired in phase 4. */
class VersionUploaded
{
    use Dispatchable;

    public function __construct(public PageVersion $version, public User $by) {}
}
