<?php

/**
 * Storage manager with local-disk default and optional cloud object storage support.
 */
class StorageManager
{
    private static $composerAutoloadChecked = false;

    public static function saveUploadedFile(array $validatedFile, string $relativeDir, string $prefix = 'upload_'): array
    {
        $extension = strtolower((string)($validatedFile['extension'] ?? 'bin'));
        $tmpName = (string)($validatedFile['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['success' => false, 'error' => 'Invalid uploaded file.'];
        }

        $filename = uniqid($prefix, true) . '.' . $extension;
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $objectPath = $relativeDir . '/' . $filename;
        $preferCloudStorage = self::shouldUseCloudStorage();

        if ($preferCloudStorage) {
            $cloudResult = self::saveToCloudStorage($tmpName, $objectPath);
            if ($cloudResult['success']) {
                return $cloudResult;
            }

            error_log('Cloud storage write failed, falling back to local storage: ' . ($cloudResult['error'] ?? 'unknown'));
        }

        $localResult = self::saveToLocalStorage($tmpName, $relativeDir, $filename);
        if ($localResult['success']) {
            return $localResult;
        }

        // If local write fails (common on read-only runtimes), try cloud as a recovery path.
        if (!$preferCloudStorage && self::hasStorageBucket()) {
            $cloudFallback = self::saveToCloudStorage($tmpName, $objectPath);
            if ($cloudFallback['success']) {
                return $cloudFallback;
            }

            $localError = (string)($localResult['error'] ?? 'Local storage failed.');
            $cloudError = (string)($cloudFallback['error'] ?? 'Cloud fallback failed.');
            return ['success' => false, 'error' => $localError . ' Cloud fallback: ' . $cloudError];
        }

        if ($preferCloudStorage && !$localResult['success']) {
            $cloudError = (string)($cloudResult['error'] ?? 'Cloud storage failed.');
            $localError = (string)($localResult['error'] ?? 'Local storage failed.');
            return ['success' => false, 'error' => $cloudError . ' Local fallback: ' . $localError];
        }

        return $localResult;
    }

    private static function shouldUseCloudStorage(): bool
    {
        $flag = strtolower(trim((string)env('USE_CLOUD_STORAGE', 'false')));
        if (in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        // App Engine standard runtime has a read-only source filesystem.
        return self::isAppEngineRuntime() && self::hasStorageBucket();
    }

    private static function saveToCloudStorage(string $tmpName, string $objectPath): array
    {
        $bucketName = trim((string)env('STORAGE_BUCKET', ''));
        if ($bucketName === '') {
            return ['success' => false, 'error' => 'STORAGE_BUCKET is not configured.'];
        }

        self::ensureComposerAutoload();

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            return ['success' => false, 'error' => 'google/cloud-storage dependency is not installed.'];
        }

        try {
            $storage = new Google\Cloud\Storage\StorageClient();
            $bucket = $storage->bucket($bucketName);

            $stream = fopen($tmpName, 'rb');
            if ($stream === false) {
                return ['success' => false, 'error' => 'Unable to read uploaded file stream.'];
            }

            try {
                $bucket->upload($stream, ['name' => $objectPath]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return [
                'success' => true,
                'path' => 'gs://' . $bucketName . '/' . $objectPath,
                'storage' => 'cloud',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function saveToLocalStorage(string $tmpName, string $relativeDir, string $filename): array
    {
        $baseDir = realpath(__DIR__ . '/../');
        if ($baseDir === false) {
            return ['success' => false, 'error' => 'Unable to resolve project root.'];
        }

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }

        if (!is_writable($targetDir)) {
            return ['success' => false, 'error' => 'Upload directory is not writable.'];
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file.'];
        }

        return [
            'success' => true,
            'path' => $relativeDir . '/' . $filename,
            'storage' => 'local',
        ];
    }

    public static function deleteStoredPath(string $storedPath): bool
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return true;
        }

        if (strpos($storedPath, 'gs://') === 0) {
            return self::deleteFromCloudStorage($storedPath);
        }

        return self::deleteFromLocalStorage($storedPath);
    }

    public static function resolvePublicUrl(string $storedPath, int $ttlSeconds = 1800): string
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $storedPath) === 1) {
            return $storedPath;
        }

        if (strpos($storedPath, 'gs://') === 0) {
            $parsed = self::parseGsPath($storedPath);
            if ($parsed === null) {
                return '';
            }

            return self::createSignedMediaUrl($storedPath, $ttlSeconds);
        }

        $normalized = ltrim(str_replace('\\', '/', $storedPath), '/');
        $publicPath = $normalized;
        if (stripos($publicPath, 'images/') === 0) {
            $publicPath = 'admin/' . $publicPath;
        }

        if (
            self::shouldUseCloudStorage()
            && self::hasStorageBucket()
            && self::isCloudBackedRelativePath($publicPath)
            && !self::localStoredPathExists($publicPath)
        ) {
            return self::createSignedMediaUrl('gs://' . trim((string) env('STORAGE_BUCKET', ''), '/') . '/' . $publicPath, $ttlSeconds);
        }

        $normalizedPath = '/' . $publicPath;

        if (function_exists('app_url')) {
            return app_url($normalizedPath);
        }

        return $normalizedPath;
    }

    public static function createSignedMediaUrl(string $storedPath, int $ttlSeconds = 1800): string
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '' || strpos($storedPath, 'gs://') !== 0 || self::parseGsPath($storedPath) === null) {
            return '';
        }

        $safeTtl = max(300, min(86400, $ttlSeconds));
        $expires = self::stableMediaExpiry($safeTtl);
        $encodedPath = self::base64UrlEncode($storedPath);
        $signature = self::signMediaPayload($encodedPath, $expires);
        $query = '?p=' . rawurlencode($encodedPath)
            . '&e=' . rawurlencode((string) $expires)
            . '&s=' . rawurlencode($signature);

        if (function_exists('app_url')) {
            return app_url('/api/media.php' . $query);
        }

        return '/api/media.php' . $query;
    }

    public static function validateSignedMediaToken(string $encodedPath, string $expires, string $signature): array
    {
        $encodedPath = trim($encodedPath);
        $expires = trim($expires);
        $signature = trim($signature);

        if ($encodedPath === '' || $expires === '' || $signature === '') {
            return ['success' => false, 'status' => 400, 'error' => 'Missing media token parameters.'];
        }

        if (!ctype_digit($expires)) {
            return ['success' => false, 'status' => 400, 'error' => 'Invalid media token expiry.'];
        }

        $expiresAt = (int) $expires;
        if ($expiresAt < time()) {
            return ['success' => false, 'status' => 410, 'error' => 'Media token has expired.'];
        }

        $expected = self::signMediaPayload($encodedPath, $expiresAt);
        if (!hash_equals($expected, $signature)) {
            return ['success' => false, 'status' => 403, 'error' => 'Invalid media token signature.'];
        }

        $storedPath = self::base64UrlDecode($encodedPath);
        if ($storedPath === null || strpos($storedPath, 'gs://') !== 0 || self::parseGsPath($storedPath) === null) {
            return ['success' => false, 'status' => 400, 'error' => 'Invalid media path.'];
        }

        return [
            'success' => true,
            'path' => $storedPath,
            'expires_at' => $expiresAt,
        ];
    }

    public static function openCloudObjectStream(string $gsPath): array
    {
        $parsed = self::parseGsPath($gsPath);
        if ($parsed === null) {
            return ['success' => false, 'status' => 400, 'error' => 'Invalid cloud storage path.'];
        }

        self::ensureComposerAutoload();
        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            return ['success' => false, 'status' => 500, 'error' => 'google/cloud-storage dependency is not installed.'];
        }

        [$bucketName, $objectName] = $parsed;

        try {
            $storage = new Google\Cloud\Storage\StorageClient();
            $bucket = $storage->bucket($bucketName);
            $object = $bucket->object($objectName);

            if (!$object->exists()) {
                return ['success' => false, 'status' => 404, 'error' => 'Media object was not found.'];
            }

            $info = $object->info();
            $stream = $object->downloadAsStream();
            $contentType = (string)($info['contentType'] ?? '');
            if ($contentType === '') {
                $contentType = self::guessContentTypeFromName($objectName);
            }

            return [
                'success' => true,
                'stream' => $stream,
                'content_type' => $contentType,
                'size' => isset($info['size']) ? (int) $info['size'] : null,
                'filename' => basename($objectName),
                'etag' => isset($info['etag']) ? trim((string) $info['etag'], '"') : '',
                'updated' => (string)($info['updated'] ?? $info['timeCreated'] ?? ''),
            ];
        } catch (Throwable $e) {
            error_log('Cloud media stream failed: ' . $e->getMessage());
            return ['success' => false, 'status' => 500, 'error' => 'Unable to read media object.'];
        }
    }

    private static function deleteFromCloudStorage(string $gsPath): bool
    {
        self::ensureComposerAutoload();

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            return false;
        }

        $parsed = self::parseGsPath($gsPath);
        if ($parsed === null) {
            return false;
        }

        [$bucketName, $objectName] = $parsed;

        try {
            $storage = new Google\Cloud\Storage\StorageClient();
            $bucket = $storage->bucket($bucketName);
            $object = $bucket->object($objectName);
            if ($object->exists()) {
                $object->delete();
            }
            return true;
        } catch (Throwable $e) {
            error_log('Cloud storage delete failed: ' . $e->getMessage());
            return false;
        }
    }

    private static function deleteFromLocalStorage(string $storedPath): bool
    {
        $baseDir = realpath(__DIR__ . '/../');
        if ($baseDir === false) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', $storedPath), '/');
        $candidates = [
            $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
            $baseDir . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return @unlink($candidate);
            }
        }

        return false;
    }

    private static function hasStorageBucket(): bool
    {
        $bucket = trim((string)env('STORAGE_BUCKET', ''));
        return $bucket !== '' && !in_array($bucket, ['YOUR_STORAGE_BUCKET', 'SET_VIA_SECRET_MANAGER'], true);
    }

    private static function isAppEngineRuntime(): bool
    {
        $gaeEnv = strtolower((string) getenv('GAE_ENV'));
        return strpos($gaeEnv, 'standard') !== false;
    }

    private static function isCloudBackedRelativePath(string $path): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        return stripos($normalized, 'uploads/') === 0 || stripos($normalized, 'admin/images/') === 0;
    }

    private static function localStoredPathExists(string $path): bool
    {
        $baseDir = realpath(__DIR__ . '/../');
        if ($baseDir === false) {
            return false;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        return is_file($candidate);
    }

    private static function signMediaPayload(string $encodedPath, int $expires): string
    {
        return hash_hmac('sha256', $encodedPath . '|' . $expires, self::mediaSigningSecret());
    }

    private static function stableMediaExpiry(int $ttlSeconds): int
    {
        $now = time();
        $windowStart = (int) (floor($now / $ttlSeconds) * $ttlSeconds);
        return $windowStart + ($ttlSeconds * 2);
    }

    private static function mediaSigningSecret(): string
    {
        $secret = '';
        if (function_exists('env')) {
            $secret = trim((string) env('MEDIA_URL_SIGNING_SECRET', ''));
        } else {
            $secret = trim((string) (getenv('MEDIA_URL_SIGNING_SECRET') ?: ''));
        }

        if ($secret !== '' && !self::isPlaceholderValue($secret)) {
            return $secret;
        }

        $fallbackParts = [
            function_exists('env') ? (string) env('APP_URL', '') : (string) (getenv('APP_URL') ?: ''),
            function_exists('env') ? (string) env('STORAGE_BUCKET', '') : (string) (getenv('STORAGE_BUCKET') ?: ''),
            function_exists('env') ? (string) env('DB_NAME', '') : (string) (getenv('DB_NAME') ?: ''),
            function_exists('env') ? (string) env('DB_PASS', '') : (string) (getenv('DB_PASS') ?: ''),
            __DIR__,
        ];

        return hash('sha256', implode('|', $fallbackParts));
    }

    private static function isPlaceholderValue(string $value): bool
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return true;
        }

        return in_array($normalized, [
            'SET_VIA_SECRET_MANAGER',
            'REPLACE_ME',
            'CHANGE_ME',
            'TODO',
            'TBD',
            'YOUR_MEDIA_URL_SIGNING_SECRET',
        ], true) || strpos($normalized, 'YOUR_') === 0;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    private static function guessContentTypeFromName(string $objectName): string
    {
        $ext = strtolower(pathinfo($objectName, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            '3gp' => 'video/3gpp',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }

    private static function ensureComposerAutoload(): void
    {
        if (self::$composerAutoloadChecked) {
            return;
        }

        self::$composerAutoloadChecked = true;
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    private static function parseGsPath(string $gsPath): ?array
    {
        if (strpos($gsPath, 'gs://') !== 0) {
            return null;
        }

        $withoutScheme = substr($gsPath, 5);
        $parts = explode('/', $withoutScheme, 2);
        if (count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }
}
