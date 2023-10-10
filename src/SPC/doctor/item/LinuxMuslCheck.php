<?php

declare(strict_types=1);

namespace SPC\doctor\item;

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
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $cross_compile_lib = "/usr/local/musl/{$arch}/lib/libc.a";
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && file_exists($cross_compile_lib)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-libc is not installed on your system', 'fix-musl');
    }

    /** @noinspection PhpUnused */
    /**
     * @throws RuntimeException
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    #[AsFixItem('fix-musl')]
    public function fixMusl(): void
    {
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        $cross_compile_lib = '/usr/local/musl/lib/libc.a';

        if (!file_exists($musl_wrapper_lib)) {
            $this->installMuslWrapper();
        }
        if (!file_exists($cross_compile_lib)) {
            $this->installMuslCrossMake($arch);
        }

        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        if (!getenv('PATH') || !str_contains(getenv('PATH'), '/usr/local/musl/bin')) {
            $fix_path = 'echo "export PATH=/usr/local/musl/bin:$PATH" >> ~/.bash_profile && export PATH=/usr/local/musl/bin:$PATH';
            shell(true)->exec($prefix . $fix_path);
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
    public function installMuslCrossMake(string $arch): void
    {
        $musl_compile_source = [
            'type' => 'url',
            'url' => "https://musl.cc/{$arch}-native.tgz",
        ];
        Downloader::downloadSource('musl-compile-native', $musl_compile_source);
        FileSystem::extractSource('musl-compile-native', DOWNLOAD_PATH . "/{$arch}-native.tgz", '/usr/local/musl');
    }
}
