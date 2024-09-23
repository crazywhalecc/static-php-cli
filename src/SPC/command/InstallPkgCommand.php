<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\exception\DownloaderException;
use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\PackageManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('install-pkg', 'Install additional packages', ['i', 'install-package'])]
class InstallPkgCommand extends BaseCommand
{
    use UnixSystemUtilTrait;

    public function configure(): void
    {
        $this->addArgument('packages', InputArgument::REQUIRED, 'The packages will be installed, comma separated');
        $this->addOption('shallow-clone', null, null, 'Clone shallow');
        $this->addOption('custom-url', 'U', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Specify custom source download url, e.g "php-src:https://downloads.php.net/~eric/php-8.3.0beta1.tar.gz"');
    }

    /**
     * @throws FileSystemException
     */
    public function handle(): int
    {
        try {
            // Use shallow-clone can reduce git resource download
            if ($this->getOption('shallow-clone')) {
                define('GIT_SHALLOW_CLONE', true);
            }

            // Process -U options
            $custom_urls = [];
            foreach ($this->input->getOption('custom-url') as $value) {
                [$pkg_name, $url] = explode(':', $value, 2);
                $custom_urls[$pkg_name] = $url;
            }

            $chosen_pkgs = array_map('trim', array_filter(explode(',', $this->getArgument('packages'))));

            // Download them
            f_mkdir(DOWNLOAD_PATH);
            $ni = 0;
            $cnt = count($chosen_pkgs);

            foreach ($chosen_pkgs as $pkg) {
                ++$ni;
                if (isset($custom_urls[$pkg])) {
                    $config = Config::getPkg($pkg);
                    $new_config = [
                        'type' => 'url',
                        'url' => $custom_urls[$pkg],
                    ];
                    if (isset($config['extract'])) {
                        $new_config['extract'] = $config['extract'];
                    }
                    if (isset($config['filename'])) {
                        $new_config['filename'] = $config['filename'];
                    }
                    logger()->info("Installing source {$pkg} from custom url [{$ni}/{$cnt}]");
                    PackageManager::installPackage($pkg, $new_config);
                } else {
                    logger()->info("Fetching package {$pkg} [{$ni}/{$cnt}]");
                    PackageManager::installPackage($pkg, Config::getPkg($pkg));
                }
            }
            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Install packages complete, used ' . $time . ' s !');
            return static::SUCCESS;
        } catch (DownloaderException $e) {
            logger()->error($e->getMessage());
            return static::FAILURE;
        } catch (WrongUsageException $e) {
            logger()->critical($e->getMessage());
            return static::FAILURE;
        }
    }
}
