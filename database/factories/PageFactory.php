<?php

namespace Database\Factories;

use App\Models\Catalog;
use App\Models\Page;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'catalog_id' => Catalog::factory(),
            'number' => fn (array $a) => (Page::where('catalog_id', $a['catalog_id'])->max('number') ?? 0) + 1,
            'current_version' => 1,
            'submitted_at' => now(),
            'waiting_since' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Page $page) {
            if ($page->versions()->count() === 0) {
                $page->versions()->create(['number' => 1, 'image_path' => "catalogos/{$page->catalog->slug}/p{$page->number}-v1.jpg"]);
            }
            if ($page->sectors()->count() === 0) {
                $page->sectors()->sync(Sector::where('slug', 'liquidos')->pluck('id'));
            }
        });
    }

    /** Still in Ogilvy's preparation: not sent to Marketing yet. */
    public function preparing(): static
    {
        return $this->state(fn () => ['submitted_at' => null, 'waiting_since' => null]);
    }

    /** @param  list<string>  $slugs */
    public function sectors(array $slugs): static
    {
        return $this->afterCreating(fn (Page $page) => $page->sectors()->sync(Sector::whereIn('slug', $slugs)->pluck('id')));
    }
}
