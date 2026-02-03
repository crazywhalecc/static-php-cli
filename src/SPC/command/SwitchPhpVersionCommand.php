<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\store\Config;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('switch-php-version', description: 'Switch downloaded PHP version')]
class SwitchPhpVersionCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument(
            'php-major-version',
            InputArgument::REQUIRED,
            'PHP major version (supported: 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5)',
            null,
            fn () => ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5']
        );
        $this->no_motd = true;

        $this->addOption('retry', 'R', InputOption::VALUE_REQUIRED, 'Set retry time when downloading failed (default: 0)', '0');
    }

    public function handle(): int
    {
        $php_ver = $this->input->getArgument('php-major-version');
        if (!in_array($php_ver, ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'])) {
            // match x.y.z
            preg_match('/^\d+\.\d+\.\d+$/', $php_ver, $matches);
            if (!$matches) {
                $this->output->writeln('<error>Invalid PHP version ' . $php_ver . ' !</error>');
                return static::FAILURE;
            }
        }

        if (LockFile::isLockFileExists('php-src')) {
            $this->output->writeln('<info>Removing old PHP source...</info>');
            LockFile::put('php-src', null);
        }

        // Download new PHP source
        $this->output->writeln('<info>Downloading PHP source...</info>');
        define('SPC_BUILD_PHP_VERSION', $php_ver);

        // retry
        $retry = intval($this->getOption('retry'));
        f_putenv('SPC_DOWNLOAD_RETRIES=' . $retry);

        Downloader::downloadSource('php-src', Config::getSource('php-src'));

        // Remove source/php-src dir
        FileSystem::removeDir(SOURCE_PATH . '/php-src');

        $this->output->writeln('<info>Switched to PHP ' . $php_ver . ' successfully!</info>');
        return static::SUCCESS;
    }
}
