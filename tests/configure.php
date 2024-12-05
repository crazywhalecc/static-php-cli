<?php

if (patch_point() === 'before-php-make') {
    // get config.status file
    $config_status = file_get_contents(SOURCE_PATH . '/php-src/config.status');
    if ($config_status === false) {
        throw patch_point_interrupt(1, 'Failed to read config.status');
    }
    if (str_contains($config_status, 'S["PHP_VERSION"]=""')) {
        throw patch_point_interrupt(1, 'Cannot find valid PHP_VERSION in config.status');
    }
    throw patch_point_interrupt(0);
}
