<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\linux\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\doctor\OptionalCheck;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\PackageManager;
use SPC\store\SourcePatcher;
use SPC\toolchain\MuslToolchain;
use SPC\toolchain\ZigToolchain;

#[OptionalCheck([self::class, 'optionalCheck'])]
class LinuxMuslCheck
{
    public static function optionalCheck(): bool
    {
        return getenv('SPC_TOOLCHAIN') === MuslToolchain::class ||
            (getenv('SPC_TOOLCHAIN') === ZigToolchain::class && !SystemUtil::isMuslDist() && !str_contains((string) getenv('SPC_TARGET'), 'gnu'));
    }

    /** @noinspection PhpUnused */
    #[AsCheckItem('if musl-wrapper is installed', limit_os: 'Linux', level: 800)]
    public function checkMusl(): CheckResult
    {
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && (file_exists('/usr/local/musl/lib/libc.a') || getenv('SPC_TOOLCHAIN') === ZigToolchain::class)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-wrapper is not installed on your system', 'fix-musl-wrapper');
    }

    #[AsCheckItem('if musl-cross-make is installed', limit_os: 'Linux', level: 799)]
    public function checkMuslCrossMake(): CheckResult
    {
        if (getenv('SPC_TOOLCHAIN') === ZigToolchain::class && !SystemUtil::isMuslDist()) {
            return CheckResult::ok();
        }
        $arch = arch2gnu(php_uname('m'));
        $cross_compile_lib = "/usr/local/musl/{$arch}-linux-musl/lib/libc.a";
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-linux-musl-gcc";
        if (file_exists($cross_compile_lib) && file_exists($cross_compile_gcc)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-cross-make is not installed on your system', 'fix-musl-cross-make');
    }

    #[AsFixItem('fix-musl-wrapper')]
    public function fixMusl(): bool
    {
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        // The hardcoded version here is to be consistent with the version compiled by `musl-cross-toolchain`.
        $musl_version_name = 'musl-1.2.5';
        $musl_source = [
            'type' => 'url',
            'url' => "https://musl.libc.org/releases/{$musl_version_name}.tar.gz",
        ];
        logger()->info('Downloading ' . $musl_source['url']);
        Downloader::downloadSource($musl_version_name, $musl_source);
        FileSystem::extractSource($musl_version_name, SPC_SOURCE_ARCHIVE, DOWNLOAD_PATH . "/{$musl_version_name}.tar.gz");

        // Apply CVE-2025-26519 patch
        SourcePatcher::patchFile('musl-1.2.5_CVE-2025-26519_0001.patch', SOURCE_PATH . "/{$musl_version_name}");
        SourcePatcher::patchFile('musl-1.2.5_CVE-2025-26519_0002.patch', SOURCE_PATH . "/{$musl_version_name}");
        logger()->info('Installing musl wrapper');
        shell()->cd(SOURCE_PATH . "/{$musl_version_name}")
            ->exec('CC=gcc CXX=g++ AR=ar LD=ld ./configure --disable-gcc-wrapper')
            ->exec('CC=gcc CXX=g++ AR=ar LD=ld make -j')
            ->exec("CC=gcc CXX=g++ AR=ar LD=ld {$prefix}make install");
        // TODO: add path using putenv instead of editing /etc/profile
        return true;
    }

    #[AsFixItem('fix-musl-cross-make')]
    public function fixMuslCrossMake(): bool
    {
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        $arch = arch2gnu(php_uname('m'));
        logger()->info("Downloading package musl-toolchain-{$arch}-linux");
        PackageManager::installPackage("musl-toolchain-{$arch}-linux");
        $pkg_root = PKG_ROOT_PATH . "/musl-toolchain-{$arch}-linux";
        shell()->exec("{$prefix}cp -rf {$pkg_root}/* /usr/local/musl");
        FileSystem::removeDir($pkg_root);
        return true;
    }
}
