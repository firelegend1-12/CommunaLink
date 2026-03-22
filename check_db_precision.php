<?php
require_once 'config/init.php';

function check_table($pdo, $table) {
    echo "\n--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

check_table($pdo, 'notifications');
check_table($pdo, 'document_requests');
check_table($pdo, 'business_transactions');
