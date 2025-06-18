<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\doctor\CheckResult;
use SPC\exception\RuntimeException;
use SPC\store\Downloader;
use SPC\store\FileSystem;

trait UnixGoCheckTrait
{
    private function checkGoAndXcaddy(): ?CheckResult
    {
        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        $goroot = getenv('GOROOT') ?: '/usr/local/go';
        $goBin = "{$goroot}/bin";
        $paths[] = $goBin;
        if ($this->findCommand('go', $paths) === null) {
            $this->installGo();
        }

        $gobin = getenv('GOBIN') ?: (getenv('HOME') . '/go/bin');
        putenv("GOBIN={$gobin}");

        $paths[] = $gobin;

        if ($this->findCommand('xcaddy', $paths) === null) {
            shell(true)->exec('go install github.com/caddyserver/xcaddy/cmd/xcaddy@latest');
        }

        return CheckResult::ok();
    }

    private function installGo(): bool
    {
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }

        $arch = php_uname('m');
        $go_arch = match ($arch) {
            'x86_64' => 'amd64',
            'aarch64' => 'arm64',
            default => $arch
        };
        $os = strtolower(PHP_OS_FAMILY);

        $go_version = '1.24.4';
        $go_filename = "go{$go_version}.{$os}-{$go_arch}.tar.gz";
        $go_url = "https://go.dev/dl/{$go_filename}";

        logger()->info("Downloading Go {$go_version} for {$go_arch}");

        try {
            // Download Go binary
            Downloader::downloadFile('go', $go_url, $go_filename);

            // Extract the tarball
            FileSystem::extractSource('go', SPC_SOURCE_ARCHIVE, DOWNLOAD_PATH . "/{$go_filename}");

            // Move to /usr/local/go
            logger()->info('Installing Go to /usr/local/go');
            shell()->exec("{$prefix}rm -rf /usr/local/go");
            shell()->exec("{$prefix}mv " . SOURCE_PATH . '/go /usr/local/');

            if (!str_contains(getenv('PATH'), '/usr/local/go/bin')) {
                logger()->info('Adding Go to PATH');
                shell()->exec("{$prefix}echo 'export PATH=\$PATH:/usr/local/go/bin' >> /etc/profile");
                putenv('PATH=' . getenv('PATH') . ':/usr/local/go/bin');
            }

            logger()->info('Go has been installed successfully');
            return true;
        } catch (RuntimeException $e) {
            logger()->error('Failed to install Go: ' . $e->getMessage());
            return false;
        }
    }
}
