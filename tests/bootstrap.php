<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/globals/internal-env.php';
require_once __DIR__ . '/mock/SPC_store.php';

\SPC\util\AttributeMapper::init();

$log_dir = SPC_LOGS_DIR;
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}
