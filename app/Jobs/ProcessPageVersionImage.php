<?php

namespace App\Jobs;

use App\Models\PageVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Print-ready JPGs arrive at 3213×6260 px and up to 5 MB. Reviewers never need that:
 * this job derives a screen copy (1400 px wide, enough for 200 % zoom) and a thumbnail
 * (360 px) next to the original, which stays untouched for the archive.
 */
class ProcessPageVersionImage implements ShouldQueue
{
    use Queueable;

    public const DISPLAY_WIDTH = 1400;

    public const THUMB_WIDTH = 360;

    public int $tries = 3;

    public function __construct(public readonly PageVersion $version) {}

    public function handle(): void
    {
        $disk = Storage::disk('local');
        $source = $this->version->image_path;
        if (! $disk->exists($source)) {
            return;
        }

        $manager = new ImageManager(new Driver);
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $source);
        $display = $base.'-display.jpg';
        $thumb = $base.'-thumb.jpg';

        $image = $manager->decodePath($disk->path($source));
        $disk->put($display, (string) $image->scaleDown(width: self::DISPLAY_WIDTH)->encode(new JpegEncoder(quality: 82)));
        $disk->put($thumb, (string) $image->scaleDown(width: self::THUMB_WIDTH)->encode(new JpegEncoder(quality: 78)));

        $this->version->forceFill(['display_path' => $display, 'thumb_path' => $thumb])->save();
    }
}
