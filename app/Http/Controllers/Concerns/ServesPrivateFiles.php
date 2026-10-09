<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Private files (artwork, attachments, logos) go out as a plain response with the bytes in memory.
 * Streaming responses need fpassthru() and ignore_user_abort(), which the hosting's PHP disables.
 */
trait ServesPrivateFiles
{
    protected function privateFile(string $path, ?string $downloadName = null, array $headers = []): Response
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline'.($downloadName ? '; filename="'.addcslashes($downloadName, '"\\').'"' : ''),
            ...$headers,
        ]);
    }
}
