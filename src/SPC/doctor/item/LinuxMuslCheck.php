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
    #[AsCheckItem('if musl-gcc is installed', limit_os: 'Linux')]
    public function checkMusl(): CheckResult
    {
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $cross_compile_lib = '/usr/local/musl/lib/libc.a';
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-gcc";
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib)) {
            // alpine doesn't need musl-compile because gcc targets musl by default
            if (SystemUtil::getOSRelease()['dist'] === 'alpine') {
                return CheckResult::ok();
            }
            if (file_exists($cross_compile_lib) || file_exists($cross_compile_gcc)) {
                return CheckResult::ok();
            }
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
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        $musl_wrapper_gcc = '/usr/local/musl/bin/musl-gcc';
        $cross_compile_lib = '/usr/local/musl/lib/libc.a';
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-gcc";

        try {
            if (!file_exists(DOWNLOAD_PATH)) {
                FileSystem::createDir(DOWNLOAD_PATH);
            }
            if (!file_exists($musl_wrapper_lib) || !file_exists($musl_wrapper_gcc)) {
                $this->installMuslWrapper();
            }
            if (SystemUtil::getOSRelease()['dist'] !== 'alpine' && (!file_exists($cross_compile_lib) || !file_exists($cross_compile_gcc))) {
                $this->installMuslCrossMake($arch);
            }

            $prefix = '';
            if (get_current_user() !== 'root') {
                $prefix = 'sudo ';
                logger()->warning('Current user is not root, using sudo for running command');
            }
            $profile = file_exists('~/.bash_profile') ? '~/.bash_profile' : '~/.profile';
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
    public function installMuslCrossMake(string $arch): void
    {
        $musl_compile_source = [
            'type' => 'url',
            'url' => "https://musl.cc/{$arch}-native.tgz",
        ];
        Downloader::downloadSource('musl-compile-native', $musl_compile_source);
        FileSystem::extractSource('musl-compile-native', DOWNLOAD_PATH . "/{$arch}-native.tgz");
        shell(true)->exec('cp -rf ' . SOURCE_PATH . '/musl-compile-native/* /usr/local/musl');
    }
}
