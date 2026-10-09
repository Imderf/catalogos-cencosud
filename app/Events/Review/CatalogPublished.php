<?php

namespace App\Events\Review;

use App\Models\Catalog;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Domain event; listeners (notifications, activity) are wired in phase 4. */
class CatalogPublished
{
    use Dispatchable;

    public function __construct(public Catalog $catalog, public User $by) {}
}
