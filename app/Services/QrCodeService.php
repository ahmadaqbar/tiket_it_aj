<?php

namespace App\Services;

use App\Models\Unit;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR Code per unit (pure PHP + GD, tanpa layanan eksternal).
 * QR hanya berisi URL /qr/{token}; tidak memberi akses ke data apa pun tanpa login.
 */
class QrCodeService
{
    public function url(Unit $unit): string
    {
        return route('qr.show', $unit->qr_token);
    }

    public function svg(Unit $unit): string
    {
        return (new QRCode($this->options(QROutputInterface::MARKUP_SVG, 5)))->render($this->url($unit));
    }

    public function png(Unit $unit, int $scale = 12): string
    {
        return (new QRCode($this->options(QROutputInterface::GDIMAGE_PNG, $scale)))->render($this->url($unit));
    }

    public function pngDataUri(Unit $unit, int $scale = 12): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($unit, $scale));
    }

    private function options(string $type, int $scale): QROptions
    {
        return new QROptions([
            'outputType' => $type,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'svgAddXmlHeader' => false,
        ]);
    }
}
