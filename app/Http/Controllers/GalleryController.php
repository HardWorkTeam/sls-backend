<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gallery\StoreAlbumRequest;
use App\Http\Requests\Gallery\UpdateAlbumRequest;
use App\Http\Requests\Gallery\UploadMediaRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\MediaItemResource;
use App\Models\Album;
use App\Models\MediaItem;
use App\Models\Wedding;
use App\Services\GalleryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GalleryController extends Controller
{
    public function __construct(private readonly GalleryService $galleryService) {}

    public function albums(Wedding $wedding): AnonymousResourceCollection
    {
        return AlbumResource::collection($this->galleryService->albums($wedding));
    }

    public function storeAlbum(StoreAlbumRequest $request, Wedding $wedding): JsonResponse
    {
        $album = $this->galleryService->createAlbum($wedding, $request->validated());

        return AlbumResource::make($album)->response()->setStatusCode(201);
    }

    public function updateAlbum(UpdateAlbumRequest $request, Wedding $wedding, Album $album): AlbumResource
    {
        return AlbumResource::make($this->galleryService->updateAlbum($album, $request->validated()));
    }

    public function destroyAlbum(Wedding $wedding, Album $album): JsonResponse
    {
        $this->galleryService->deleteAlbum($album);

        return response()->json(['message' => 'Album deleted.']);
    }

    public function media(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return MediaItemResource::collection(
            $this->galleryService->media(
                $wedding,
                $request->query('album_id') !== null ? (int) $request->query('album_id') : null,
                $request->query('media_type'),
                (int) $request->query('per_page', '24'),
            ),
        );
    }

    public function upload(UploadMediaRequest $request, Wedding $wedding): JsonResponse
    {
        $mediaItem = $this->galleryService->upload(
            $wedding,
            $request->user(),
            $request->file('file'),
            $request->validated('album_id') !== null ? (int) $request->validated('album_id') : null,
            $request->has('is_public') ? $request->boolean('is_public') : null,
        );

        return MediaItemResource::make($mediaItem)->response()->setStatusCode(201);
    }

    public function updateMedia(Request $request, Wedding $wedding, MediaItem $mediaItem): MediaItemResource
    {
        return MediaItemResource::make(
            $this->galleryService->updateMedia($mediaItem, $request->boolean('is_public')),
        );
    }

    public function destroyMedia(Wedding $wedding, MediaItem $mediaItem): JsonResponse
    {
        $this->galleryService->deleteMedia($mediaItem);

        return response()->json(['message' => 'Media deleted.']);
    }
}
