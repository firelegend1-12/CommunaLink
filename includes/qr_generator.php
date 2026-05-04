<?php
require_once __DIR__ . '/qrlib.php';

function generate_qr_datauri(string $text, int $size = 150): string {
    $tempBase = tempnam(sys_get_temp_dir(), 'qr_');
    if ($tempBase === false) {
        return '';
    }

    if (function_exists('imagecreate') && function_exists('imagepng')) {
        $tempFile = $tempBase . '.png';
        QRcode::png($text, $tempFile, QR_ECLEVEL_M, 4, 2);
        $data = file_get_contents($tempFile) ?: '';
        @unlink($tempFile);
        @unlink($tempBase);
        return $data === '' ? '' : ('data:image/png;base64,' . base64_encode($data));
    }

    $tempFile = $tempBase . '.svg';
    QRcode::svg($text, $tempFile, QR_ECLEVEL_M, 4, 2);
    $data = file_get_contents($tempFile) ?: '';
    @unlink($tempFile);
    @unlink($tempBase);
    return $data === '' ? '' : ('data:image/svg+xml;base64,' . base64_encode($data));
}
