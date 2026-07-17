<?php

/**
 * Fix orphaned media items (album_id = null) by assigning them
 * to a default public "General Gallery" album per wedding,
 * and ensure all existing uploaded media items are set to public by default.
 */

use App\Models\Album;
use App\Models\MediaItem;

$orphans = MediaItem::whereNull('album_id')->get();

if ($orphans->isNotEmpty()) {
    foreach ($orphans->groupBy('wedding_id') as $weddingId => $items) {
        $album = Album::firstOrCreate(
            ['wedding_id' => $weddingId, 'name' => 'General Gallery'],
            ['description' => 'Default wedding gallery photos', 'is_public' => true]
        );

        MediaItem::whereIn('id', $items->pluck('id'))->update(['album_id' => $album->id, 'is_public' => true]);
        echo "Wedding #{$weddingId}: assigned {$items->count()} items to album '{$album->name}' (id={$album->id})\n";
    }
}

$updatedCount = MediaItem::where('is_public', false)->update(['is_public' => true]);
echo "Updated {$updatedCount} media items to is_public = true.\n";
echo "Done!\n";
