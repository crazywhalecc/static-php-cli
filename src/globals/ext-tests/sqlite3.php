<?php

declare(strict_types=1);

$sqlite = new SQLite3(':memory:');
$enabled = $sqlite->querySingle("SELECT sqlite_compileoption_used('ENABLE_COLUMN_METADATA')");

if ((int) $enabled !== 1) {
    throw new RuntimeException('SQLite was not built with SQLITE_ENABLE_COLUMN_METADATA.');
}
