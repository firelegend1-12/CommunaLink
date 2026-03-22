<?php
require_once 'config/init.php';

try {
    echo "--- Table: notifications ---\n";
    $stmt = $pdo->query("DESCRIBE notifications");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }

    echo "\n--- Table: document_requests columns ---\n";
    $stmt = $pdo->query("DESCRIBE document_requests");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($row['Field'], ['or_number', 'payment_status', 'payment_date'])) {
            print_r($row);
        }
    }

    echo "\n--- Table: business_transactions columns ---\n";
    $stmt = $pdo->query("DESCRIBE business_transactions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (in_array($row['Field'], ['or_number', 'payment_status', 'payment_date'])) {
            print_r($row);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
