<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes for shop boards: SVG for screens and print, PNG for download.
 * Medium error correction so a slightly scratched or folded card still scans.
 */
class QrCode
{
    public static function svg(string $text, int $size = 320): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        $svg = $writer->writeString($text, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());

        // Remove the XML declaration line so the SVG can be placed straight into HTML.
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    public static function png(string $text, int $size = 1024): string
    {
        $writer = new Writer(new GDLibRenderer($size, 4));

        return $writer->writeString($text, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());
    }
}
