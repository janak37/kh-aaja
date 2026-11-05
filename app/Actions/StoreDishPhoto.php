<?php

namespace App\Actions;

use App\Models\Shop;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns an uploaded dish photo into a small WebP file:
 * fixes phone rotation, max 800px wide, saved on the public disk.
 */
class StoreDishPhoto
{
    public const MAX_WIDTH = 800;

    public const QUALITY = 75;

    public function handle(UploadedFile $file, Shop $shop): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $image = imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('The photo could not be read.');
        }

        $image = $this->fixRotation($image, $file);
        $image = $this->scaleDown($image);

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::QUALITY);
        $webp = (string) ob_get_clean();

        $path = 'dishes/'.$shop->id.'/'.Str::random(24).'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    /**
     * Phone cameras often save photos sideways plus a "rotate me" tag.
     */
    private function fixRotation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true) || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function scaleDown(GdImage $image): GdImage
    {
        if (imagesx($image) <= self::MAX_WIDTH) {
            return $image;
        }

        $scaled = imagescale($image, self::MAX_WIDTH);

        return $scaled instanceof GdImage ? $scaled : $image;
    }
}
