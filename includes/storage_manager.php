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
                fclose($stream);
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

            [$bucketName, $objectName] = $parsed;

            self::ensureComposerAutoload();
            if (class_exists('Google\\Cloud\\Storage\\StorageClient')) {
                try {
                    $storage = new Google\Cloud\Storage\StorageClient();
                    $bucket = $storage->bucket($bucketName);
                    $object = $bucket->object($objectName);

                    if ($object->exists()) {
                        $safeTtl = max(60, $ttlSeconds);
                        return (string) $object->signedUrl(
                            new DateTimeImmutable('+' . $safeTtl . ' seconds'),
                            ['version' => 'v4']
                        );
                    }
                } catch (Throwable $e) {
                    error_log('Failed to generate signed URL for cloud object: ' . $e->getMessage());
                }
            }

            $encodedObject = implode('/', array_map('rawurlencode', explode('/', $objectName)));
            return 'https://storage.googleapis.com/' . rawurlencode($bucketName) . '/' . $encodedObject;
        }

        $normalizedPath = '/' . ltrim(str_replace('\\', '/', $storedPath), '/');

        if (function_exists('app_url')) {
            return app_url($normalizedPath);
        }

        return $normalizedPath;
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
        return trim((string)env('STORAGE_BUCKET', '')) !== '';
    }

    private static function isAppEngineRuntime(): bool
    {
        $gaeEnv = strtolower((string) getenv('GAE_ENV'));
        return strpos($gaeEnv, 'standard') !== false;
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
