<?php

namespace Ayvazyan10\Imagic\Http\Controllers;

use Ayvazyan10\Imagic\Http\Controllers\Concerns\ResolvesOwnedMedia;
use Ayvazyan10\Imagic\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaContentController extends Controller
{
    use ResolvesOwnedMedia;

    public function __construct(private readonly MediaStorage $storage)
    {
    }

    public function content(Request $request, string $media): StreamedResponse
    {
        return $this->stream($request, $media, false);
    }

    public function thumbnail(Request $request, string $media): StreamedResponse
    {
        return $this->stream($request, $media, true);
    }

    private function stream(Request $request, string $uuid, bool $thumbnail): StreamedResponse
    {
        $asset = $this->ownedAsset($request->user(), $uuid);
        $stream = $this->storage->readStream($asset, $thumbnail);
        $filename = str_replace(['"', "\r", "\n"], '', $asset->name);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, [
            'Content-Type' => $asset->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
