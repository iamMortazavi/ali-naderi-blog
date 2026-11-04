<?php

namespace App\Services\Content;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Smaller WebP copies of an uploaded image (480 and 960 pixels wide), made once and reused.
 * They sit next to the original in a _variants folder. GD is available on cPanel hosts.
 */
class ImageVariants
{
    private const WIDTHS = [480, 960];

    private const SOURCES = ['jpg', 'jpeg', 'png', 'webp'];

    /** 1200×630 JPEG for link previews (Telegram, X, WhatsApp, search results). */
    public function shareImage(string $path): ?array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, self::SOURCES, true)) {
            return null;
        }
        $disk = Storage::disk('public');
        $variant = dirname($path).'/_variants/'.pathinfo($path, PATHINFO_FILENAME).'-share.jpg';
        try {
            if (! $disk->exists($variant) || $disk->lastModified($variant) < $disk->lastModified($path)) {
                $disk->makeDirectory(dirname($variant));
                ImageManager::usingDriver(Driver::class)
                    ->decodePath($disk->path($path))
                    ->cover(1200, 630)
                    ->encodeUsingFormat(Format::JPEG, quality: 82)
                    ->save($disk->path($variant));
            }
        } catch (Throwable $e) {
            Log::warning("Share image failed for {$path}: {$e->getMessage()}");

            return null;
        }

        return ['url' => $disk->url($variant), 'width' => 1200, 'height' => 630];
    }

    /** @return array{src:string,srcset:string,width:int,height:int}|null */
    public function for(string $path): ?array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, self::SOURCES, true)) {
            return null;
        }
        $disk = Storage::disk('public');
        try {
            [$width, $height] = getimagesize($disk->path($path));
            $set = [];
            foreach (self::WIDTHS as $w) {
                $w = min($w, $width);
                $variant = dirname($path).'/_variants/'.pathinfo($path, PATHINFO_FILENAME)."-{$w}.webp";
                if (! $disk->exists($variant) || $disk->lastModified($variant) < $disk->lastModified($path)) {
                    $disk->makeDirectory(dirname($variant));
                    ImageManager::usingDriver(Driver::class)
                        ->decodePath($disk->path($path))
                        ->scaleDown($w)
                        ->encodeUsingFormat(Format::WEBP, quality: 80)
                        ->save($disk->path($variant));
                }
                $set[$w] = $disk->url($variant);
            }
            $largest = max(array_keys($set));

            return [
                'src' => $set[$largest],
                'srcset' => collect($set)->map(fn ($url, $w) => "{$url} {$w}w")->implode(', '),
                'width' => $largest,
                'height' => (int) round($height * $largest / $width),
            ];
        } catch (Throwable $e) {
            Log::warning("Image variants failed for {$path}: {$e->getMessage()}");

            return null;
        }
    }
}
