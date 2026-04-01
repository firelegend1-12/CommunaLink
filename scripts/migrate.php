<?php

/**
 * SQL migration runner with schema version tracking.
 *
 * Usage:
 *   php scripts/migrate.php
 *   php scripts/migrate.php --dry-run
 *   php scripts/migrate.php --path=migrations
 */

require_once __DIR__ . '/../config/database.php';

$argv = $_SERVER['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$migrationsPath = __DIR__ . '/../migrations';

foreach ($argv as $arg) {
    if (strpos($arg, '--path=') === 0) {
        $provided = substr($arg, 7);
        if ($provided !== '') {
            $migrationsPath = realpath(__DIR__ . '/../' . ltrim($provided, '/\\')) ?: $migrationsPath;
        }
    }
}

if (!is_dir($migrationsPath)) {
    fwrite(STDERR, "Migration path not found: {$migrationsPath}\n");
    exit(1);
}

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL PRIMARY KEY,
    checksum VARCHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    execution_ms INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$appliedStmt = $pdo->query('SELECT filename, checksum FROM schema_migrations');
$applied = [];
foreach ($appliedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $applied[(string)$row['filename']] = (string)$row['checksum'];
}

$files = glob($migrationsPath . DIRECTORY_SEPARATOR . '*.sql');
sort($files, SORT_STRING);

if (empty($files)) {
    echo "No migration files found in {$migrationsPath}\n";
    exit(0);
}

$pending = 0;
$appliedCount = 0;

foreach ($files as $fullPath) {
    $filename = basename($fullPath);
    $sql = file_get_contents($fullPath);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$filename}\n");
        exit(1);
    }

    $checksum = hash('sha256', $sql);

    if (isset($applied[$filename])) {
        if ($applied[$filename] !== $checksum) {
            fwrite(STDERR, "Checksum mismatch for applied migration: {$filename}\n");
            exit(1);
        }
        echo "SKIP  {$filename}\n";
        continue;
    }

    $pending++;

    if ($dryRun) {
        echo "PLAN  {$filename}\n";
        continue;
    }

    $start = microtime(true);

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);

        $elapsedMs = (int)round((microtime(true) - $start) * 1000);

        $insert = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum, execution_ms) VALUES (?, ?, ?)');
        $insert->execute([$filename, $checksum, $elapsedMs]);

        $pdo->commit();

        $appliedCount++;
        echo "APPLY {$filename} ({$elapsedMs} ms)\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAIL  {$filename}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($dryRun) {
    echo "Dry run complete. Pending migrations: {$pending}\n";
    exit(0);
}

echo "Migration complete. Applied {$appliedCount} migration(s).\n";
