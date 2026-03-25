<?php
require_once 'config/database.php';
require_once 'config/init.php';
$stmt = $pdo->query('DESCRIBE users');
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
echo implode(', ', $cols) . PHP_EOL;
echo 'Has reset_token: ' . (in_array('reset_token', $cols) ? 'YES' : 'NO') . PHP_EOL;
echo 'Has reset_token_expires: ' . (in_array('reset_token_expires', $cols) ? 'YES' : 'NO') . PHP_EOL;
echo 'Has status: ' . (in_array('status', $cols) ? 'YES' : 'NO') . PHP_EOL;
