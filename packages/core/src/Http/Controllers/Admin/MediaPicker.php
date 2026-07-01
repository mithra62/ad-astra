<?php

namespace AdAstra\Http\Controllers\Admin;

use AdAstra\Models\Media as MediaModel;
use AdAstra\Models\Media\Library;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Paginated/filterable JSON listing of Media for the Media field's picker UI.
 *
 * Limits the response to Media in the requested library_id[] (which the
 * caller derives from the field's allowed-libraries setting). The server
 * also enforces that the libraries actually exist — bad IDs are dropped.
 */
class MediaPicker extends Controller
{
    private const DEFAULT_PER_PAGE = 24;

    private const MAX_PER_PAGE = 100;

    private const THUMBNAIL_KEY = 'picker';

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'library_id' => ['required', 'array', 'min:1'],
            'library_id.*' => ['integer'],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ]);

        $libraryIds = Library::whereIn('id', (array)$request->input('library_id'))
            ->pluck('id')
            ->all();

        if (empty($libraryIds)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => (int)$request->input('per_page', self::DEFAULT_PER_PAGE),
                ],
            ]);
        }

        $perPage = (int)$request->input('per_page', self::DEFAULT_PER_PAGE);
        $q = trim((string)$request->input('q', ''));

        $query = MediaModel::query()
            ->with('library:id,name')
            ->whereIn('library_id', $libraryIds)
            ->orderByDesc('id');

        if ($q !== '') {
            // Escape SQL LIKE wildcards in user input, then declare the escape
            // character explicitly so the protection works across drivers
            // (SQLite ignores escapes without an ESCAPE clause).
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw("name LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("original_name LIKE ? ESCAPE '\\'", [$like]);
            });
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (MediaModel $media): array {
            return [
                'id' => $media->id,
                'name' => $media->name,
                'original_name' => $media->original_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'library_id' => $media->library_id,
                'library_name' => $media->library?->name,
                'url' => $this->safeUrl($media),
                'thumbnail_url' => $this->thumbnailUrl($media),
                'is_image' => $media->isImage(),
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    private function safeUrl(MediaModel $media): ?string
    {
        try {
            return $media->url();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns a small thumbnail URL for images, or null for non-images.
     * Triggers a transformation request (idempotent — re-uses the existing
     * record if already generated).
     */
    private function thumbnailUrl(MediaModel $media): ?string
    {
        if (!$media->isImage()) {
            return null;
        }

        try {
            $existing = $media->transformation(self::THUMBNAIL_KEY);
            if ($existing) {
                return Storage::disk($existing->disk)->url($existing->path);
            }

            $media->transform(self::THUMBNAIL_KEY, [
                'width' => 240,
                'height' => 240,
                'mode' => 'cover',
            ]);
        } catch (Throwable) {
            // Fall through to original URL.
        }

        return $this->safeUrl($media);
    }
}
