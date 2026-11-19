<?php

namespace App\Services\Records;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Inline SVG QR codes for printed ID cards and certificates (crisp at any print size).
 */
class QrCodeRenderer
{
    public function svg(string $text, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 64))),
            new SvgImageBackEnd,
        );

        return (string) preg_replace('/^<\?xml.*?\?>\s*/', '', (new Writer($renderer))->writeString($text));
    }
}
