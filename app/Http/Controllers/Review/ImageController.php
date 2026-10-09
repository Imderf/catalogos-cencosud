<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Concerns\ServesPrivateFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Client artwork never sits on a public URL: every image is served through the session.
 * Any signed-in user may see any page (the model restricts acting, not viewing).
 */
class ImageController extends Controller
{
    use ServesPrivateFiles;

    public function show(string $path): Response
    {
        abort_unless(str_starts_with($path, 'catalogos/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return $this->privateFile($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
