<?php

namespace App\Http\Controllers;

use App\Domain\Platform\Models\Media;
use App\Domain\Video\Services\PrivateMediaService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function __construct(
        private readonly PrivateMediaService $mediaService,
    ) {}

    public function show(Request $request, Media $media): StreamedResponse|Response
    {
        return $this->mediaService->stream($request, $media);
    }
}
