<?php

declare(strict_types=1);

use SPC\builder\freebsd\SystemUtil as BSDSystemUtil;
use SPC\builder\linux\SystemUtil as LinuxSystemUtil;
use SPC\builder\macos\SystemUtil as MacOSSystemUtil;
use SPC\builder\windows\SystemUtil as WindowsSystemUtil;
use SPC\store\FileSystem;
use SPC\util\GlobalEnvManager;

define('BUILD_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_ROOT_PATH')) ? $a : (WORKING_DIR . '/buildroot')));
define('BUILD_INCLUDE_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_INCLUDE_PATH')) ? $a : (BUILD_ROOT_PATH . '/include')));
define('BUILD_LIB_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_LIB_PATH')) ? $a : (BUILD_ROOT_PATH . '/lib')));
define('BUILD_BIN_PATH', FileSystem::convertPath(is_string($a = getenv('BUILD_BIN_PATH')) ? $a : (BUILD_ROOT_PATH . '/bin')));
define('PKG_ROOT_PATH', FileSystem::convertPath(is_string($a = getenv('PKG_ROOT_PATH')) ? $a : (WORKING_DIR . '/pkgroot')));
define('SOURCE_PATH', FileSystem::convertPath(is_string($a = getenv('SOURCE_PATH')) ? $a : (WORKING_DIR . '/source')));
define('DOWNLOAD_PATH', FileSystem::convertPath(is_string($a = getenv('DOWNLOAD_PATH')) ? $a : (WORKING_DIR . '/downloads')));
define('CPU_COUNT', match (PHP_OS_FAMILY) {
    'Windows' => (string) WindowsSystemUtil::getCpuCount(),
    'Darwin' => (string) MacOSSystemUtil::getCpuCount(),
    'Linux' => (string) LinuxSystemUtil::getCpuCount(),
    'BSD' => (string) BSDSystemUtil::getCpuCount(),
    default => 1,
});
define('GNU_ARCH', arch2gnu(php_uname('m')));
define('MAC_ARCH', match ($_im8a = arch2gnu(php_uname('m'))) {
    'aarch64' => 'arm64',
    default => $_im8a
});

// deprecated variables
define('SEPARATED_PATH', [
    '/' . pathinfo(BUILD_LIB_PATH)['basename'], // lib
    '/' . pathinfo(BUILD_INCLUDE_PATH)['basename'], // include
    BUILD_ROOT_PATH,
]);

// add these to env vars with same name
GlobalEnvManager::putenv('BUILD_ROOT_PATH=' . BUILD_ROOT_PATH);
GlobalEnvManager::putenv('BUILD_INCLUDE_PATH=' . BUILD_INCLUDE_PATH);
GlobalEnvManager::putenv('BUILD_LIB_PATH=' . BUILD_LIB_PATH);
GlobalEnvManager::putenv('BUILD_BIN_PATH=' . BUILD_BIN_PATH);
GlobalEnvManager::putenv('PKG_ROOT_PATH=' . PKG_ROOT_PATH);
GlobalEnvManager::putenv('SOURCE_PATH=' . SOURCE_PATH);
GlobalEnvManager::putenv('DOWNLOAD_PATH=' . DOWNLOAD_PATH);
GlobalEnvManager::putenv('CPU_COUNT=' . CPU_COUNT);
GlobalEnvManager::putenv('SPC_ARCH=' . php_uname('m'));
GlobalEnvManager::putenv('GNU_ARCH=' . GNU_ARCH);
GlobalEnvManager::putenv('MAC_ARCH=' . MAC_ARCH);
