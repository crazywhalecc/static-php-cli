<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\windows\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;
use SPC\store\Downloader;
use SPC\store\FileSystem;

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
        if (!file_exists(PHP_SDK_PATH . DIRECTORY_SEPARATOR . 'phpsdk-starter.bat')) {
            return CheckResult::fail('php-sdk-binary-tools not downloaded', 'install-php-sdk');
        }
        return CheckResult::ok(PHP_SDK_PATH);
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
        if (file_exists(BUILD_ROOT_PATH . '\perl\perl\bin\perl.exe')) {
            return CheckResult::ok(BUILD_ROOT_PATH . '\perl\perl\bin\perl.exe');
        }
        if (($path = SystemUtil::findCommand('perl.exe')) === null) {
            return CheckResult::fail('perl not found in path.', 'install-perl');
        }
        if (!str_contains(implode('', cmd()->execWithResult(quote($path) . ' -v')[1]), 'MSWin32')) {
            return CheckResult::fail($path . ' is not built for msvc.', 'install-perl');
        }
        return CheckResult::ok();
    }

    #[AsFixItem('install-php-sdk')]
    public function installPhpSdk(): bool
    {
        try {
            FileSystem::removeDir(PHP_SDK_PATH);
            cmd(true)->exec('git.exe clone --depth 1 https://github.com/php/php-sdk-binary-tools.git ' . PHP_SDK_PATH);
        } catch (RuntimeException) {
            return false;
        }
        return true;
    }

    #[AsFixItem('install-nasm')]
    public function installNasm(): bool
    {
        // The hardcoded version here is to be consistent with the version compiled by `musl-cross-toolchain`.
        $nasm_ver = '2.16.01';
        $nasm_dist = "nasm-{$nasm_ver}";
        $source = [
            'type' => 'url',
            'url' => "https://www.nasm.us/pub/nasm/releasebuilds/{$nasm_ver}/win64/{$nasm_dist}-win64.zip",
        ];
        logger()->info('Downloading ' . $source['url']);
        Downloader::downloadSource('nasm', $source);
        FileSystem::extractSource('nasm', DOWNLOAD_PATH . "\\{$nasm_dist}-win64.zip");
        copy(SOURCE_PATH . "\\nasm\\{$nasm_dist}\\nasm.exe", PHP_SDK_PATH . '\bin\nasm.exe');
        copy(SOURCE_PATH . "\\nasm\\{$nasm_dist}\\ndisasm.exe", PHP_SDK_PATH . '\bin\ndisasm.exe');
        return true;
    }

    #[AsFixItem('install-perl')]
    public function installPerl(): bool
    {
        $url = 'https://github.com/StrawberryPerl/Perl-Dist-Strawberry/releases/download/SP_5380_5361/strawberry-perl-5.38.0.1-64bit-portable.zip';
        $source = [
            'type' => 'url',
            'url' => $url,
        ];
        logger()->info("Downloading {$url}");
        Downloader::downloadSource('strawberry-perl', $source);
        FileSystem::extractSource('strawberry-perl', DOWNLOAD_PATH . '\strawberry-perl-5.38.0.1-64bit-portable.zip', '../buildroot/perl');
        return true;
    }
}
