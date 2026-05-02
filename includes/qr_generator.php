<?php
require_once __DIR__ . '/qrlib.php';

function generate_qr_datauri(string $text, int $size = 150): string {
    $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
    QRcode::png($text, $tempFile, QR_ECLEVEL_M, 4, 2);
    $data = file_get_contents($tempFile);
    unlink($tempFile);
    return 'data:image/png;base64,' . base64_encode($data);
}
