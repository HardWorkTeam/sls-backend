<?php
/**
 * Fix orphaned media items (album_id = null) by assigning them
 * to a default public "General Gallery" album per wedding.
 */

use App\Models\Album;
use App\Models\MediaItem;

$orphans = MediaItem::whereNull('album_id')->get();

if ($orphans->isEmpty()) {
    echo "No orphaned media items found.\n";
    return;
}

$count = 0;

foreach ($orphans->groupBy('wedding_id') as $weddingId => $items) {
    $album = Album::firstOrCreate(
        ['wedding_id' => $weddingId, 'name' => 'General Gallery'],
        ['description' => 'Default wedding gallery photos', 'is_public' => true]
    );

    MediaItem::whereIn('id', $items->pluck('id'))->update(['album_id' => $album->id]);
    $count += $items->count();

    echo "Wedding #{$weddingId}: assigned {$items->count()} items to album '{$album->name}' (id={$album->id})\n";
}

echo "\nDone! Fixed {$count} orphaned media items.\n";
