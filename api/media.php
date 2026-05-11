<?php

require_once __DIR__ . '/../config/env_loader.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage_manager.php';

$validation = StorageManager::validateSignedMediaToken(
    (string)($_GET['p'] ?? ''),
    (string)($_GET['e'] ?? ''),
    (string)($_GET['s'] ?? '')
);

if (empty($validation['success'])) {
    $status = (int)($validation['status'] ?? 400);
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo (string)($validation['error'] ?? 'Invalid media request.');
    exit;
}

$streamResult = StorageManager::openCloudObjectStream((string)$validation['path']);
if (empty($streamResult['success'])) {
    $status = (int)($streamResult['status'] ?? 500);
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo (string)($streamResult['error'] ?? 'Unable to load media.');
    exit;
}

$closeStream = static function ($stream): void {
    if (is_resource($stream)) {
        fclose($stream);
        return;
    }

    if (is_object($stream) && method_exists($stream, 'close')) {
        $stream->close();
    }
};

$expiresAt = (int)($validation['expires_at'] ?? time());
$remaining = max(0, $expiresAt - time());
$cacheSeconds = min(3600, $remaining);
$contentType = (string)($streamResult['content_type'] ?? 'application/octet-stream');
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($streamResult['filename'] ?? 'media')) ?: 'media';
$etagSource = (string)($streamResult['etag'] ?? '');
if ($etagSource === '') {
    $etagSource = sha1((string)$validation['path'] . '|' . (string)($streamResult['size'] ?? '') . '|' . (string)($streamResult['updated'] ?? ''));
}
$etag = '"' . trim($etagSource, '"') . '"';
$lastModified = '';
$updated = (string)($streamResult['updated'] ?? '');
if ($updated !== '') {
    $updatedTimestamp = strtotime($updated);
    if ($updatedTimestamp !== false) {
        $lastModified = gmdate('D, d M Y H:i:s', $updatedTimestamp) . ' GMT';
    }
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=' . $cacheSeconds);
header('ETag: ' . $etag);
if ($lastModified !== '') {
    header('Last-Modified: ' . $lastModified);
}
header('X-Content-Type-Options: nosniff');

$requestEtag = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$requestModifiedSince = trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
if ($requestEtag !== '' && hash_equals($etag, $requestEtag)) {
    $closeStream($streamResult['stream'] ?? null);
    http_response_code(304);
    exit;
}
if ($lastModified !== '' && $requestModifiedSince !== '') {
    $requestModifiedTimestamp = strtotime($requestModifiedSince);
    $lastModifiedTimestamp = strtotime($lastModified);
    if ($requestModifiedTimestamp !== false && $lastModifiedTimestamp !== false && $requestModifiedTimestamp >= $lastModifiedTimestamp) {
        $closeStream($streamResult['stream'] ?? null);
        http_response_code(304);
        exit;
    }
}

if (isset($streamResult['size']) && $streamResult['size'] !== null) {
    header('Content-Length: ' . (int)$streamResult['size']);
}

$stream = $streamResult['stream'];
if (is_resource($stream)) {
    while (!feof($stream)) {
        echo fread($stream, 8192);
    }
    fclose($stream);
    exit;
}

if (is_object($stream) && method_exists($stream, 'eof') && method_exists($stream, 'read')) {
    while (!$stream->eof()) {
        echo $stream->read(8192);
    }
    exit;
}

http_response_code(500);
echo 'Unsupported media stream.';
