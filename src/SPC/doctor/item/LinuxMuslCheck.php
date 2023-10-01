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
        $file = "/usr/local/musl/{$arch}/lib/libc.a";
        if (file_exists($file)) {
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
        $distro = SystemUtil::getOSRelease();
        $source = [
            'type' => 'git',
            'url' => 'https://github.com/richfelker/musl-cross-make',
            'rev' => 'master',
        ];
        Downloader::downloadSource('musl-cross-make', $source);
        $arch = arch2gnu(php_uname('m')) === 'x86_64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl';
        $install_musl_wrapper_cmd = 'cd ' . DOWNLOAD_PATH . '/musl-cross-make && \
                       make install TARGET=' . $arch . ' OUTPUT=/usr/local/musl -j && \
                       if [[ ! "$PATH" =~ (^|:)"/usr/local/musl/bin"(:|$) ]]; then echo "export PATH=/usr/local/musl/bin:$PATH" >> ~/.bash_profile
                       fi && \
                       source ~/.bash_profile';
        $rhel_install = 'wget https://musl.libc.org/releases/musl-1.2.4.tar.gz && tar -zxvf musl-1.2.4.tar.gz && \
                         rm -f musl-1.2.4.tar.gz && cd musl-1.2.4 && 
                         if [[ ! "$PATH" =~ (^|:)"/usr/local/musl/bin"(:|$) ]]; then echo "export PATH=/usr/local/musl/bin:$PATH" >> ~/.bash_profile
                         fi && \
                         ./configure --enable-wrapper=gcc && \
                         make -j && make install && cd .. && rm -rf musl-1.2.4';
        $install_cmd = match ($distro['dist']) {
            'ubuntu', 'debian' => 'apt-get install musl musl-tools -y',
            'alpine' => 'apk add musl musl-utils musl-dev',
            'redhat' => $rhel_install,
            default => throw new RuntimeException('Current linux distro does not have an auto-install script for musl packages yet.'),
        };
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        try {
            shell(true)
                ->exec($prefix . $install_cmd)
                ->exec($install_musl_wrapper_cmd);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
