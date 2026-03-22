<?php
require_once 'config/database.php';
function getDesc($pdo, $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $output = "Table: $table\n";
    foreach ($fields as $f) {
        $output .= "{$f['Field']} | {$f['Type']} | {$f['Null']} | {$f['Default']}\n";
    }
    return $output . "\n";
}
file_put_contents('tmp_schema_output.txt', getDesc($pdo, 'business_transactions') . getDesc($pdo, 'business_permits'));
?>
