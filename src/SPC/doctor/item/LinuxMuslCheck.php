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

            $prefix = '';
            if (get_current_user() !== 'root') {
                $prefix = 'sudo ';
                logger()->warning('Current user is not root, using sudo for running command');
            }
            $profile = '/etc/profile';
            if (file_exists('~/.bash_profile')) {
                $profile = '~/.bash_profile';
            } elseif (file_exists('~/.profile')) {
                $profile = '~/.profile';
            }
            if (!file_exists($profile)) {
                shell(true)->exec($prefix . 'touch ' . $profile);
            }
            if (!getenv('PATH') || !str_contains(getenv('PATH'), '/usr/local/musl/bin')) {
                $fix_path = 'echo "export PATH=/usr/local/musl/bin:$PATH" >> ' . $profile . ' && export PATH=/usr/local/musl/bin:$PATH';
                shell(true)->exec($prefix . $fix_path);
            }
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
        $musl_source = [
            'type' => 'url',
            'url' => 'https://musl.libc.org/releases/musl-1.2.4.tar.gz',
        ];
        Downloader::downloadSource('musl-1.2.4', $musl_source);
        FileSystem::extractSource('musl-1.2.4', DOWNLOAD_PATH . '/musl-1.2.4.tar.gz');
        $musl_gcc_install_cmd = 'cd ' . SOURCE_PATH . '/musl-1.2.4 && \
                         ./configure --enable-wrapper=gcc && \
                         make -j && make install';
        shell(true)->exec($prefix . $musl_gcc_install_cmd);
    }

    /**
     * @throws DownloaderException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function installMuslCrossMake(): void
    {
        $arch = arch2gnu(php_uname('m'));
        $musl_compile_source = [
            'type' => 'url',
            'url' => "https://musl.cc/{$arch}-linux-musl-cross.tgz",
        ];
        Downloader::downloadSource('musl-compile', $musl_compile_source);
        FileSystem::extractSource('musl-compile', DOWNLOAD_PATH . "/{$arch}-linux-musl-cross.tgz");
        shell(true)->exec('cp -rf ' . SOURCE_PATH . '/musl-compile/* /usr/local/musl');
    }
}
