<?php

namespace App\Actions\Review;

use App\Enums\CatalogKind;
use App\Events\Review\CatalogPublished;
use App\Jobs\ProcessPageVersionImage;
use App\Models\Brand;
use App\Models\Catalog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ogilvy uploads the pages and tags their sectors. Every page needs at least one sector:
 * that is where the report counts and each commercial's permission come from.
 */
class PublishCatalog
{
    /**
     * @param  list<array{image: UploadedFile|string, sectors: list<int>}>  $pages  image is an uploaded file or a path on the public disk
     */
    public function handle(User $by, Brand $brand, string $name, array $pages, CatalogKind $kind = CatalogKind::Octavilla): Catalog
    {
        Gate::forUser($by)->authorize('catalogs.manage');

        if ($pages === []) {
            throw new InvalidArgumentException('Sube al menos una página.');
        }
        foreach ($pages as $i => $p) {
            if (empty($p['sectors'])) {
                throw new InvalidArgumentException('Toda página necesita al menos un sector (página '.($i + 1).').');
            }
        }

        return DB::transaction(function () use ($by, $brand, $name, $pages, $kind) {
            $slug = Str::slug($name).'-'.Str::lower(Str::random(6));
            $catalog = Catalog::create([
                'brand_id' => $brand->id, 'slug' => $slug, 'name' => $name, 'kind' => $kind->value,
                'created_by' => $by->id, 'published_at' => now(),
            ]);

            foreach ($pages as $i => $p) {
                /* Pages start in Ogilvy's preparation: no clock until they are sent to Marketing. */
                $page = Page::create(['catalog_id' => $catalog->id, 'number' => $i + 1, 'current_version' => 1, 'submitted_at' => null, 'waiting_since' => null]);
                $page->sectors()->sync($p['sectors']);
                $version = $page->versions()->create([
                    'number' => 1, 'uploaded_by' => $by->id,
                    'image_path' => self::storeImage($catalog, $page->number, 1, $p['image']),
                ]);
                ProcessPageVersionImage::dispatch($version);
            }

            CatalogPublished::dispatch($catalog, $by);

            return $catalog->load('pages.versions', 'pages.sectors');
        });
    }

    /** Stores an uploaded image (or keeps an existing local-disk path) under catalogos/{slug}/ on the private disk. */
    public static function storeImage(Catalog $catalog, int $pageNumber, int $version, UploadedFile|string $image): string
    {
        if (is_string($image)) {
            return $image;
        }
        $ext = $image->getClientOriginalExtension() ?: 'jpg';
        $name = sprintf('p%02d-v%d.%s', $pageNumber, $version, $ext);

        return Storage::disk('local')->putFileAs("catalogos/{$catalog->slug}", $image, $name);
    }
}
