<?php

declare(strict_types=1);

use SPC\store\FileSystem;
use ZM\Logger\ConsoleLogger;

define('WORKING_DIR', getcwd());
define('ROOT_DIR', dirname(__DIR__, 2));

// CLI start time
define('START_TIME', microtime(true));

define('BUILD_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_ROOT_PATH')) ? $a : (WORKING_DIR . '/buildroot')));
define('SOURCE_PATH', FileSystem::convertPath(is_string($a = getenv('SOURCE_PATH')) ? $a : (WORKING_DIR . '/source')));
define('DOWNLOAD_PATH', FileSystem::convertPath(is_string($a = getenv('DOWNLOAD_PATH')) ? $a : (WORKING_DIR . '/downloads')));
define('PKG_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('PKG_ROOT_PATH')) ? $a : (WORKING_DIR . '/pkgroot')));
define('BUILD_BIN_PATH', FileSystem::convertPath(is_string($a = getenv('INSTALL_BIN_PATH')) ? $a : (BUILD_ROOT_PATH . '/bin')));
define('BUILD_LIB_PATH', FileSystem::convertPath(is_string($a = getenv('INSTALL_LIB_PATH')) ? $a : (BUILD_ROOT_PATH . '/lib')));
define('BUILD_INCLUDE_PATH', FileSystem::convertPath(is_string($a = getenv('INSTALL_INCLUDE_PATH')) ? $a : (BUILD_ROOT_PATH . '/include')));
define('SEPARATED_PATH', [
    '/' . pathinfo(BUILD_LIB_PATH)['basename'], // lib
    '/' . pathinfo(BUILD_INCLUDE_PATH)['basename'], // include
    BUILD_ROOT_PATH,
]);

if (PHP_OS_FAMILY === 'Windows') {
    define('PHP_SDK_PATH', is_string($a = getenv('PHP_SDK_PATH')) ? $a : (WORKING_DIR . DIRECTORY_SEPARATOR . 'php-sdk-binary-tools'));
}

// for windows, prevent calling Invoke-WebRequest and wsl command
const SPC_CURL_EXEC = PHP_OS_FAMILY === 'Windows' ? 'curl.exe' : 'curl';
const SPC_GIT_EXEC = PHP_OS_FAMILY === 'Windows' ? 'git.exe' : 'git';

// dangerous command
const DANGER_CMD = [
    'rm',
    'rmdir',
];

// spc internal extensions
const SPC_INTERNAL_EXTENSIONS = [
    'core',
    'hash',
    'json',
    'pcre',
    'reflection',
    'spl',
    'standard',
];

// spc extension alias
const SPC_EXTENSION_ALIAS = [
    'zend opcache' => 'opcache',
    'zend-opcache' => 'opcache',
    'zendopcache' => 'opcache',
];

// spc lock type
const SPC_LOCK_SOURCE = 1;      // lock source
const SPC_LOCK_PRE_BUILT = 2;   // lock pre-built

// file replace strategy
const REPLACE_FILE_STR = 1;
const REPLACE_FILE_PREG = 2;
const REPLACE_FILE_USER = 3;

// library build status
const LIB_STATUS_OK = 0;
const LIB_STATUS_ALREADY = 1;
const LIB_STATUS_BUILD_FAILED = 2;
const LIB_STATUS_INSTALL_FAILED = 3;

// build target type
const BUILD_TARGET_NONE = 0;    // no target
const BUILD_TARGET_CLI = 1;     // build cli
const BUILD_TARGET_MICRO = 2;   // build micro
const BUILD_TARGET_FPM = 4;     // build fpm
const BUILD_TARGET_EMBED = 8;   // build embed
const BUILD_TARGET_ALL = 15;    // build all

// doctor error fix policy
const FIX_POLICY_DIE = 1;       // die directly
const FIX_POLICY_PROMPT = 2;    // if it can be fixed, ask fix or not
const FIX_POLICY_AUTOFIX = 3;   // if it can be fixed, just fix automatically

// pkgconf patch policy
const PKGCONF_PATCH_PREFIX = 1;
const PKGCONF_PATCH_EXEC_PREFIX = 2;
const PKGCONF_PATCH_LIBDIR = 4;
const PKGCONF_PATCH_INCLUDEDIR = 8;
const PKGCONF_PATCH_CUSTOM = 16;
const PKGCONF_PATCH_ALL = 31;

// autoconf flags
const AUTOCONF_LIBS = 1;
const AUTOCONF_CFLAGS = 2;
const AUTOCONF_CPPFLAGS = 4;
const AUTOCONF_LDFLAGS = 8;
const AUTOCONF_ALL = 15;

ConsoleLogger::$date_format = 'H:i:s';
