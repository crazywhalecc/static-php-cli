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
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $cross_compile_lib = "/usr/local/musl/{$arch}/lib/libc.a";
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && file_exists($cross_compile_lib)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-libc is not installed on your system', 'fix-musl');
    }

    /**
     * @throws RuntimeException
     * @throws DownloaderException
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    #[AsFixItem('fix-musl')]
    public function fixMusl(): bool
    {
        $musl_source = [
            'type' => 'url',
            'url' => 'https://musl.libc.org/releases/musl-1.2.4.tar.gz',
        ];
        $musl_cross_compile_source = [
            'type' => 'git',
            'url' => 'https://github.com/richfelker/musl-cross-make',
            'rev' => 'master',
        ];
        Downloader::downloadSource('musl-cross-make', $musl_cross_compile_source);
        Downloader::downloadSource('musl-1.2.4', $musl_source);
        FileSystem::extractSource('musl-1.2.4', DOWNLOAD_PATH . '/musl-1.2.4.tar.gz');
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $install_musl_wrapper_cmd = 'cd ' . DOWNLOAD_PATH . '/musl-cross-make && \
                       make install TARGET=' . $arch . ' OUTPUT=/usr/local/musl -j';
        $musl_install = 'cd ' . SOURCE_PATH . '/musl-1.2.4 && \
                         ./configure --enable-wrapper=gcc && \
                         make -j && make install';
        $musl_install_cmd = match (SystemUtil::getOSRelease()['dist']) {
            'ubuntu', 'debian' => 'apt-get install musl musl-tools -y',
            'alpine' => 'apk add musl musl-utils musl-dev',
            default => $musl_install,
        };
        $fix_path = 'if [[ ! "$PATH" =~ (^|:)"/usr/local/musl/bin"(:|$) ]]; then echo "export PATH=/usr/local/musl/bin:$PATH" >> ~/.bash_profile
                     fi && \
                     source ~/.bash_profile';
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            shell(true)
                ->exec($prefix . $musl_install_cmd)
                ->exec($install_musl_wrapper_cmd)
                ->exec($prefix . $fix_path);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
