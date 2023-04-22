<?php

declare(strict_types=1);

use ZM\Logger\ConsoleLogger;

define('WORKING_DIR', getcwd());
define('ROOT_DIR', realpath(__DIR__ . '/../..'));

// CLI start time
define('START_TIME', microtime(true));

define('BUILD_ROOT_PATH', is_string($a = getenv('BUILD_ROOT_PATH')) ? $a : (WORKING_DIR . '/buildroot'));
define('SOURCE_PATH', is_string($a = getenv('SOURCE_PATH')) ? $a : (WORKING_DIR . '/source'));
define('DOWNLOAD_PATH', is_string($a = getenv('DOWNLOAD_PATH')) ? $a : (WORKING_DIR . '/downloads'));

define('BUILD_LIB_PATH', is_string($a = getenv('INSTALL_LIB_PATH')) ? $a : (BUILD_ROOT_PATH . '/lib'));
const BUILD_DEPS_PATH = BUILD_ROOT_PATH;
define('BUILD_INCLUDE_PATH', is_string($a = getenv('INSTALL_INCLUDE_PATH')) ? $a : (BUILD_ROOT_PATH . '/include'));
define('SEPARATED_PATH', [
    '/' . pathinfo(BUILD_LIB_PATH)['basename'], // lib
    '/' . pathinfo(BUILD_INCLUDE_PATH)['basename'], // include
    BUILD_ROOT_PATH,
]);

// dangerous command
const DANGER_CMD = [
    'rm',
    'rmdir',
];

// file replace strategy
const REPLACE_FILE_STR = 1;
const REPLACE_FILE_PREG = 2;
const REPLACE_FILE_USER = 3;

// build sapi type
const BUILD_MICRO_NONE = 0;
const BUILD_MICRO_ONLY = 1;
const BUILD_MICRO_BOTH = 2;

// library build status
const BUILD_STATUS_OK = 0;
const BUILD_STATUS_ALREADY = 1;
const BUILD_STATUS_FAILED = 2;

// build target type
const BUILD_TYPE_CLI = 1;
const BUILD_TYPE_MICRO = 2;
const BUILD_TYPE_FPM = 3;

// doctor error fix policy
const FIX_POLICY_DIE = 1;       // die directly
const FIX_POLICY_PROMPT = 2;    // if it can be fixed, ask fix or not
const FIX_POLICY_AUTOFIX = 3;   // if it can be fixed, just fix automatically

ConsoleLogger::$date_format = 'H:i:s';
