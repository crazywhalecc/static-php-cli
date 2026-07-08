<?php

declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE spc_column_metadata_test (id INTEGER)');

$stmt = $pdo->query('SELECT id FROM spc_column_metadata_test');
if ($stmt === false) {
    throw new RuntimeException('Failed to query SQLite metadata test table.');
}

$metadata = $stmt->getColumnMeta(0);
if (($metadata['table'] ?? null) !== 'spc_column_metadata_test') {
    throw new RuntimeException('PDO SQLite column metadata does not include the origin table.');
}
