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
    /**
     * @throws WrongUsageException
     */
    #[AsCheckItem('if musl-libc is installed', limit_os: 'Linux')]
    public function checkMusl(): CheckResult
    {
        if (SystemUtil::isMuslDist()) {
            return CheckResult::ok();
        }
        $arch = arch2gnu(php_uname('m'));
        $cross_compile_lib = "/usr/local/musl/{$arch}-linux-musl/lib/libc.a";
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-linux-musl-gcc";
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && file_exists($cross_compile_lib) && file_exists($cross_compile_gcc)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-libc is not installed on your system', 'fix-musl');
    }

    /** @noinspection PhpUnused */
    /**
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    #[AsFixItem('fix-musl')]
    public function fixMusl(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        $cross_compile_lib = "/usr/local/musl/{$arch}-linux-musl/lib/libc.a";
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-linux-musl-gcc";

        try {
            if (!file_exists(DOWNLOAD_PATH)) {
                FileSystem::createDir(DOWNLOAD_PATH);
            }
            if (!file_exists($musl_wrapper_lib)) {
                $this->installMuslWrapper();
            }
            if (!file_exists($cross_compile_lib) || !file_exists($cross_compile_gcc)) {
                $this->installMuslCrossMake();
            }
            // TODO: add path using putenv instead of editing /etc/profile
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function installMuslWrapper(): void
    {
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
        Downloader::downloadSource($musl_version_name, $musl_source);
        FileSystem::extractSource($musl_version_name, DOWNLOAD_PATH . "/{$musl_version_name}.tar.gz");
        shell(true)->cd(SOURCE_PATH . "/{$musl_version_name}")
            ->exec('./configure')
            ->exec('make -j')
            ->exec("{$prefix}make install");
    }

    /**
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function installMuslCrossMake(): void
    {
        $arch = arch2gnu(php_uname('m'));
        $musl_compile_source = [
            'type' => 'url',
            'url' => "https://dl.static-php.dev/static-php-cli/deps/musl-toolchain/{$arch}-musl-toolchain.tgz",
        ];
        logger()->info('Downloading ' . $musl_compile_source['url']);
        Downloader::downloadSource('musl-compile', $musl_compile_source);
        logger()->info('Extracting musl-cross');
        FileSystem::extractSource('musl-compile', DOWNLOAD_PATH . "/{$arch}-musl-toolchain.tgz");
        shell(true)->exec('cp -rf ' . SOURCE_PATH . '/musl-compile/* /usr/local/musl');
    }
}
