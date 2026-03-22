<?php
require_once 'config/database.php';
try {
    $pdo->exec("ALTER TABLE business_transactions ADD COLUMN IF NOT EXISTS permit_id INT(11) AFTER resident_id");
    echo "Success: permit_id column added to business_transactions.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
