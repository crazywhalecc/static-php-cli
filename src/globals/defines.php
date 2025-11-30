<?php

declare(strict_types=1);

define('WORKING_DIR', getcwd());
define('ROOT_DIR', dirname(__DIR__, 2));
putenv('WORKING_DIR=' . WORKING_DIR);
putenv('ROOT_DIR=' . ROOT_DIR);

// CLI start time
define('START_TIME', microtime(true));

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

// spc download lock type
const SPC_DOWNLOAD_SOURCE = 1;      // lock source
const SPC_DOWNLOAD_PRE_BUILT = 2;   // lock pre-built
const SPC_DOWNLOAD_PACKAGE = 3; // lock as package

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
const BUILD_TARGET_FRANKENPHP = 16;   // build frankenphp
const BUILD_TARGET_CGI = 32;   // build cgi
const BUILD_TARGET_ALL = BUILD_TARGET_CLI | BUILD_TARGET_MICRO | BUILD_TARGET_FPM | BUILD_TARGET_EMBED | BUILD_TARGET_FRANKENPHP | BUILD_TARGET_CGI;    // build all

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

// spc download status
const SPC_DOWNLOAD_STATUS_SKIPPED = 1;
const SPC_DOWNLOAD_STATUS_SUCCESS = 2;
const SPC_DOWNLOAD_STATUS_FAILED = 3;

// spc download source type
const SPC_SOURCE_ARCHIVE = 'archive'; // download as archive
const SPC_SOURCE_GIT = 'git'; // download as git repository
const SPC_SOURCE_LOCAL = 'local'; // download as local directory

const SPC_STATUS_EXTRACTED = 0;
const SPC_STATUS_INSTALLED = 0;
const SPC_STATUS_BUILT = 0;
const SPC_STATUS_ALREADY_EXTRACTED = 1;
const SPC_STATUS_ALREADY_INSTALLED = 1;
const SPC_STATUS_ALREADY_BUILT = 1;

const SPC_DOWNLOAD_TYPE_DISPLAY_NAME = [
    'bitbuckettag' => 'BitBucket',
    'filelist' => 'website',
    'git' => 'git',
    'ghrel' => 'GitHub release',
    'ghtar', 'ghtagtar' => 'GitHub tarball',
    'local' => 'local dir',
    'pie' => 'PHP Installer for Extensions',
    'url' => 'url',
    'php-release' => 'php.net',
    'custom' => 'custom downloader',
];
