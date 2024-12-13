<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\windows\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\store\PackageManager;

class WindowsToolCheckList
{
    #[AsCheckItem('if Visual Studio are installed', limit_os: 'Windows', level: 999)]
    public function checkVS(): ?CheckResult
    {
        $vs_ver = SystemUtil::findVisualStudio();
        if ($vs_ver === false) {
            return CheckResult::fail('Visual Studio not installed, please install VS 2022/2019.');
        }
        return CheckResult::ok($vs_ver['version'] . ' ' . $vs_ver['dir']);
    }

    #[AsCheckItem('if git are installed', limit_os: 'Windows', level: 998)]
    public function checkGit(): ?CheckResult
    {
        if (SystemUtil::findCommand('git.exe') === null) {
            return CheckResult::fail('Git not installed, please install git for windows manually, see: https://git-scm.com/download/win');
            // return CheckResult::fail('Git not installed, see https://static-php.dev/en/guide/windows-setup.html');
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if php-sdk-binary-tools are downloaded', limit_os: 'Windows', level: 997)]
    public function checkSDK(): ?CheckResult
    {
        if (!file_exists(getenv('PHP_SDK_PATH') . DIRECTORY_SEPARATOR . 'phpsdk-starter.bat')) {
            return CheckResult::fail('php-sdk-binary-tools not downloaded', 'install-php-sdk');
        }
        return CheckResult::ok(getenv('PHP_SDK_PATH'));
    }

    #[AsCheckItem('if git associated command exists', limit_os: 'Windows', level: 996)]
    public function checkGitPatch(): ?CheckResult
    {
        if (($path = SystemUtil::findCommand('patch.exe')) === null) {
            return CheckResult::fail('Git patch (minGW command) not found in path. You need to add "C:\Program Files\Git\usr\bin" in Path.');
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if nasm installed', limit_os: 'Windows', level: 995)]
    public function checkNasm(): ?CheckResult
    {
        if (SystemUtil::findCommand('nasm.exe', include_sdk_bin: true) === null) {
            return CheckResult::fail('nasm.exe not found in path.', 'install-nasm');
        }
        return CheckResult::ok();
    }

    #[AsCheckItem('if perl(strawberry) installed', limit_os: 'Windows', level: 994)]
    public function checkPerl(): ?CheckResult
    {
        $arch = arch2gnu(php_uname('m'));
        if (file_exists(PKG_ROOT_PATH . '\strawberry-perl-' . $arch . '-win\perl\bin\perl.exe')) {
            return CheckResult::ok(PKG_ROOT_PATH . '\strawberry-perl-' . $arch . '-win\perl\bin\perl.exe');
        }
        if (($path = SystemUtil::findCommand('perl.exe')) === null) {
            return CheckResult::fail('perl not found in path.', 'install-perl');
        }
        if (!str_contains(implode('', cmd()->execWithResult(quote($path) . ' -v', false)[1]), 'MSWin32')) {
            return CheckResult::fail($path . ' is not built for msvc.', 'install-perl');
        }
        return CheckResult::ok();
    }

    #[AsFixItem('install-php-sdk')]
    public function installPhpSdk(): bool
    {
        try {
            FileSystem::removeDir(getenv('PHP_SDK_PATH'));
            cmd(true)->exec('git.exe clone --depth 1 https://github.com/php/php-sdk-binary-tools.git ' . getenv('PHP_SDK_PATH'));
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }

    #[AsFixItem('install-nasm')]
    public function installNasm(): bool
    {
        PackageManager::installPackage('nasm-x86_64-win');
        return true;
    }

    #[AsFixItem('install-perl')]
    public function installPerl(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        PackageManager::installPackage("strawberry-perl-{$arch}-win");
        return true;
    }
}
