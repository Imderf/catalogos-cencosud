<?php

namespace App\Events\Review;

use App\Models\Catalog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/** Ogilvy finished preparing pages and sent them to Marketing: the review starts. */
class PagesSubmitted
{
    use Dispatchable;

    /** @param  Collection<int, Page>  $pages */
    public function __construct(public Catalog $catalog, public Collection $pages, public User $by) {}
}
