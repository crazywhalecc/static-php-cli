<?php

/**
 * You can only include this file once from your app, not run it directly.
 * This file will initialize the basic environment for SPC functions.
 *
 * Please note that this file must be included after autoload.php is included,
 * because it depends on some classes from the autoloader.
 */

declare(strict_types=1);

use Psr\Log\LogLevel;
use StaticPHP\Registry\Registry;
use StaticPHP\Util\GlobalEnvManager;
use ZM\Logger\ConsoleLogger;

// If this file is run directly, show an error message
if (!debug_backtrace()) {
    echo "This file is not meant to be run directly. Please only run in the project!\n";
    exit(1);
}

// init SPC internal environment variables
require_once ROOT_DIR . '/src/globals/internal-env.php';

// init environment variables
GlobalEnvManager::init();

// init console logger
global $ob_logger;
ConsoleLogger::$date_format = 'H:i:s';
ConsoleLogger::$format = '[%date% %level_long%] %body%';
$ob_logger = new ConsoleLogger(LogLevel::WARNING);

$ob_logger->addLogCallback(function ($level, &$output, &$message, &$context, bool $shouldLog) {
    global $spc_log_filters;
    if (!is_array($spc_log_filters)) {
        $spc_log_filters = [];
    }
    // filter message and context
    $output = str_replace($spc_log_filters, '***', $output);
    $message = str_replace($spc_log_filters, '***', $message);
    $context = array_map(function ($item) use ($spc_log_filters) {
        if (is_string($item)) {
            return str_replace($spc_log_filters, '***', $item);
        }
        return $item;
    }, $context);
    return true;
});

// setup log file
if (filter_var(getenv('SPC_ENABLE_LOG_FILE'), FILTER_VALIDATE_BOOLEAN)) {
    // init spc log files
    $log_dir = SPC_LOGS_DIR;
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    } elseif (!filter_var(getenv('SPC_PRESERVE_LOG'), FILTER_VALIDATE_BOOLEAN)) {
        // Clean up old log files
        $files = glob($log_dir . '/*.log');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Use a single shared handle (see spc_log_stream) so the file is opened exactly once;
    // on Windows a second concurrent open fails while child processes hold an inherited handle.
    $ob_logger->addLogCallback(function ($level, $output) {
        spc_write_log(spc_log_stream(SPC_OUTPUT_LOG), strip_ansi_colors($output) . "\n");
        return true;
    });
}

// load core registry
Registry::loadRegistry(ROOT_DIR . '/spc.registry.yml');
// in vendor mode, auto-load the local working directory registry if it exists
if (spc_mode(SPC_MODE_VENDOR) && file_exists(WORKING_DIR . '/spc.registry.yml')) {
    Registry::loadRegistry(WORKING_DIR . '/spc.registry.yml');
}
// load registries from environment variable SPC_REGISTRIES
Registry::loadFromEnvOrOption();
