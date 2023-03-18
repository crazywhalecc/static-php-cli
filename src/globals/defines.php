<?php

declare(strict_types=1);

// 工作目录
use ZM\Logger\ConsoleLogger;

define('WORKING_DIR', getcwd());
const ROOT_DIR = __DIR__ . '/../..';

// 程序启动时间
define('START_TIME', microtime(true));

// 规定目录
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

// 危险的命令额外用 notice 级别提醒
const DANGER_CMD = [
    'rm',
    'rmdir',
];

// 替换方案
const REPLACE_FILE_STR = 1;
const REPLACE_FILE_PREG = 2;
const REPLACE_FILE_USER = 3;

// 编译输出类型
const BUILD_MICRO_NONE = 0;
const BUILD_MICRO_ONLY = 1;
const BUILD_MICRO_BOTH = 2;

// 编译状态
const BUILD_STATUS_OK = 0;
const BUILD_STATUS_ALREADY = 1;
const BUILD_STATUS_FAILED = 2;

ConsoleLogger::$date_format = 'H:i:s';
