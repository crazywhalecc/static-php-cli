<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\linux\SystemUtil;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Downloader;
use SPC\store\FileSystem;

class LinuxMuslCheck
{
    /** @noinspection PhpUnused */
    #[AsCheckItem('if musl-wrapper is installed', limit_os: 'Linux', level: 800)]
    public function checkMusl(): CheckResult
    {
        if (SystemUtil::isMuslDist()) {
            return CheckResult::ok('musl-based distro, skipped');
        }

        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && file_exists('/usr/local/musl/bin/musl-gcc')) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-wrapper is not installed on your system', 'fix-musl-wrapper');
    }

    #[AsCheckItem('if musl-cross-make is installed', limit_os: 'Linux', level: 799)]
    public function checkMuslCrossMake(): CheckResult
    {
        if (SystemUtil::isMuslDist()) {
            return CheckResult::ok('musl-based distro, skipped');
        }
        $arch = arch2gnu(php_uname('m'));
        $cross_compile_lib = "/usr/local/musl/{$arch}-linux-musl/lib/libc.a";
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-linux-musl-gcc";
        if (file_exists($cross_compile_lib) && file_exists($cross_compile_gcc)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-cross-make is not installed on your system', 'fix-musl-cross-make');
    }

    /** @noinspection PhpUnused */
    /**
     * @throws DownloaderException
     * @throws FileSystemException
     */
    #[AsFixItem('fix-musl-wrapper')]
    public function fixMusl(): bool
    {
        try {
            $prefix = '';
            if (get_current_user() !== 'root') {
                $prefix = 'sudo ';
                logger()->warning('Current user is not root, using sudo for running command');
            }
            // The hardcoded version here is to be consistent with the version compiled by `musl-cross-toolchain`.
            $musl_version_name = 'musl-1.2.4';
            $musl_source = [
                'type' => 'url',
                'url' => "https://musl.libc.org/releases/{$musl_version_name}.tar.gz",
            ];
            logger()->info('Downloading ' . $musl_source['url']);
            Downloader::downloadSource($musl_version_name, $musl_source);
            FileSystem::extractSource($musl_version_name, DOWNLOAD_PATH . "/{$musl_version_name}.tar.gz");
            logger()->info('Installing musl wrapper');
            shell()->cd(SOURCE_PATH . "/{$musl_version_name}")
                ->exec('./configure')
                ->exec('make -j')
                ->exec("{$prefix}make install");
            // TODO: add path using putenv instead of editing /etc/profile
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /** @noinspection PhpUnused */
    /**
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    #[AsFixItem('fix-musl-cross-make')]
    public function fixMuslCrossMake(): bool
    {
        try {
            $prefix = '';
            if (get_current_user() !== 'root') {
                $prefix = 'sudo ';
                logger()->warning('Current user is not root, using sudo for running command');
            }
            $arch = arch2gnu(php_uname('m'));
            $musl_compile_source = [
                'type' => 'url',
                'url' => "https://dl.static-php.dev/static-php-cli/deps/musl-toolchain/{$arch}-musl-toolchain.tgz",
            ];
            logger()->info('Downloading ' . $musl_compile_source['url']);
            Downloader::downloadSource('musl-compile', $musl_compile_source);
            logger()->info('Extracting musl-cross');
            FileSystem::extractSource('musl-compile', DOWNLOAD_PATH . "/{$arch}-musl-toolchain.tgz");
            shell()->exec($prefix . 'cp -rf ' . SOURCE_PATH . '/musl-compile/* /usr/local/musl');
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
