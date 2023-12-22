<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\windows\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;

class WindowsToolCheckList
{
    #[AsCheckItem('if git are installed', limit_os: 'Windows', level: 999)]
    public function checkGit(): ?CheckResult
    {
        if (SystemUtil::findCommand('git.exe') === null) {
            return CheckResult::fail('Git not installed, see https://static-php.dev/en/guide/windows-setup.html');
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if php-sdk-binary-tools2 are downloaded', limit_os: 'Windows', level: 998)]
    public function checkSDK(): ?CheckResult
    {
        if (!file_exists(PHP_SDK_PATH . DIRECTORY_SEPARATOR . 'phpsdk-starter.bat')) {
            return CheckResult::fail('php-sdk-binary-tools not downloaded', 'install-php-sdk');
        }
        return CheckResult::ok(PHP_SDK_PATH);
    }

    #[AsFixItem('install-php-sdk')]
    public function installPhpSdk(): bool
    {
        try {
            cmd(true)->exec('git clone https://github.com/php/php-sdk-binary-tools.git ' . PHP_SDK_PATH);
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }
}
