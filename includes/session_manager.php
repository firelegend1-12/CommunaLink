<?php

if (!function_exists('should_use_database_sessions')) {
    function should_use_database_sessions(): bool
    {
        if (!function_exists('env')) {
            require_once __DIR__ . '/../config/env_loader.php';
        }

        $driver = strtolower(trim((string) env('SESSION_DRIVER', '')));
        if (in_array($driver, ['db', 'database'], true)) {
            return true;
        }
        if (in_array($driver, ['file', 'files', 'native'], true)) {
            return false;
        }

        $appEnv = strtolower(trim((string) env('APP_ENV', 'production')));
        return in_array($appEnv, ['production', 'prod', 'staging'], true);
    }
}

if (!function_exists('initialize_database_session_handler')) {
    function initialize_database_session_handler(PDO $pdo): bool
    {
        static $initialized = false;
        if ($initialized) {
            return true;
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `php_sessions` (
                `session_id` VARCHAR(128) NOT NULL,
                `session_data` MEDIUMBLOB NOT NULL,
                `last_activity` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`session_id`),
                KEY `idx_php_sessions_last_activity` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            session_set_save_handler(new DatabaseSessionHandler($pdo), true);
            $initialized = true;
            return true;
        } catch (Throwable $e) {
            error_log('Failed to initialize database session handler: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('switch_active_session_to_database')) {
    function switch_active_session_to_database(PDO $pdo): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $sessionId = session_id();
        $snapshot = $_SESSION;

        session_write_close();

        if (!initialize_database_session_handler($pdo)) {
            if ($sessionId !== '') {
                session_id($sessionId);
            }
            @session_start();
            return false;
        }

        if ($sessionId !== '') {
            session_id($sessionId);
        }
        session_start();

        if (!is_array($_SESSION)) {
            $_SESSION = [];
        }

        foreach ((array) $snapshot as $key => $value) {
            if (!array_key_exists($key, $_SESSION)) {
                $_SESSION[$key] = $value;
            }
        }

        return true;
    }
}

if (!function_exists('ensure_session_storage')) {
    function ensure_session_storage(PDO $pdo): void
    {
        if (!should_use_database_sessions()) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            switch_active_session_to_database($pdo);
            return;
        }

        initialize_database_session_handler($pdo);
    }
}

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare('SELECT session_data FROM php_sessions WHERE session_id = ? LIMIT 1');
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
            return $data !== false ? (string) $data : '';
        } catch (Throwable $e) {
            error_log('Session read failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO php_sessions (session_id, session_data, last_activity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)');
            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable $e) {
            error_log('Session write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE session_id = ?');
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            error_log('Session destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $cutoff = time() - max(60, $max_lifetime);
            $stmt = $this->pdo->prepare('DELETE FROM php_sessions WHERE last_activity < ?');
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('Session GC failed: ' . $e->getMessage());
            return false;
        }
    }
}
