<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse|Response
    {
        $disk = Storage::disk(config('calendars.export.disk'));
        $path = config('calendars.export.path');

        if (! $disk->exists($path)) {
            return response()->json([
                'message' => "Le calendrier n'est pas encore disponible : " . $path,
            ], Response::HTTP_NOT_FOUND);
        }

        $lastModified = $disk->lastModified($path);
        $etag = md5($lastModified.$path);

        if ($request->header('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED);
        }

        return response()
            ->json(json_decode($disk->get($path), true))
            ->header('ETag', $etag)
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified).' GMT')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    private function relativePath(string $configuredPath): string
    {
        return str_replace('storage/', '', $configuredPath);
    }
}
