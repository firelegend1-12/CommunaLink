<?php

/**
 * Storage manager with local-disk default and optional cloud object storage support.
 */
class StorageManager
{
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

        if (self::shouldUseCloudStorage()) {
            $cloudResult = self::saveToCloudStorage($tmpName, $objectPath);
            if ($cloudResult['success']) {
                return $cloudResult;
            }

            error_log('Cloud storage write failed, falling back to local storage: ' . ($cloudResult['error'] ?? 'unknown'));
        }

        return self::saveToLocalStorage($tmpName, $relativeDir, $filename);
    }

    private static function shouldUseCloudStorage(): bool
    {
        $flag = strtolower(trim((string)env('USE_CLOUD_STORAGE', 'false')));
        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    private static function saveToCloudStorage(string $tmpName, string $objectPath): array
    {
        $bucketName = trim((string)env('STORAGE_BUCKET', ''));
        if ($bucketName === '') {
            return ['success' => false, 'error' => 'STORAGE_BUCKET is not configured.'];
        }

        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            return ['success' => false, 'error' => 'google/cloud-storage dependency is not installed.'];
        }

        try {
            $storage = new Google\Cloud\Storage\StorageClient();
            $bucket = $storage->bucket($bucketName);
            $bucket->upload(fopen($tmpName, 'r'), ['name' => $objectPath]);

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

    private static function deleteFromCloudStorage(string $gsPath): bool
    {
        if (!class_exists('Google\\Cloud\\Storage\\StorageClient')) {
            return false;
        }

        $withoutScheme = substr($gsPath, 5);
        $parts = explode('/', $withoutScheme, 2);
        if (count($parts) < 2) {
            return false;
        }

        $bucketName = $parts[0];
        $objectName = $parts[1];

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
}
