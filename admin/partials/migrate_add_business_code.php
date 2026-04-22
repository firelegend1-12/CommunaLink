<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

if (PHP_SAPI !== 'cli') {
    require_login();

    if (!require_permission('all_pages')) {
        http_response_code(403);
        exit('Unauthorized');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (!headers_sent()) {
            header('Allow: POST');
        }
        http_response_code(405);
        exit('Method not allowed');
    }

    if (!csrf_validate()) {
        http_response_code(403);
        exit('Invalid security token.');
    }
}

try {
    // Check if business_code column exists
    $colCheck = $pdo->query("SHOW COLUMNS FROM businesses LIKE 'business_code'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE businesses ADD COLUMN business_code VARCHAR(20) UNIQUE AFTER id");
        echo "Added business_code column. ";
    } else {
        echo "business_code column already exists. ";
    }

    // Backfill business_code for existing records
    $stmt = $pdo->query("SELECT id FROM businesses WHERE business_code IS NULL OR business_code = ''");
    $to_update = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($to_update as $id) {
        $code = 'BIZ-' . str_pad($id, 4, '0', STR_PAD_LEFT);
        // Ensure code is unique
        $check = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE business_code = ?");
        $check->execute([$code]);
        if ($check->fetchColumn() == 0) {
            $update = $pdo->prepare("UPDATE businesses SET business_code = ? WHERE id = ?");
            $update->execute([$code, $id]);
        } else {
            // If somehow not unique, append random digits
            $code .= '-' . rand(100,999);
            $update = $pdo->prepare("UPDATE businesses SET business_code = ? WHERE id = ?");
            $update->execute([$code, $id]);
        }
    }
    echo "Migration complete. business_code column checked and codes backfilled.";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
} 