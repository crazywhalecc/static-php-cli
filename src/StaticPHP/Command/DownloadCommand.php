<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\DownloaderOptions;
use StaticPHP\Registry\ArtifactLoader;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Util\DependencyResolver;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('download')]
class DownloadCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'artifacts',
            InputArgument::OPTIONAL,
            'Specific artifacts to download, comma separated, e.g "php-src,openssl,curl"',
            suggestedValues: function (CompletionInput $input) {
                $input_val = $input->getCompletionValue();
                $all_names = ArtifactLoader::getLoadedArtifactNames();
                // filter by input value
                return array_filter($all_names, fn ($name) => str_starts_with($name, $input_val));
            },
        );

        // 2.x compatible options
        $this->addOption('shallow-clone', null, null, '(deprecated) Clone shallowly repositories when downloading sources');
        $this->addOption('for-extensions', 'e', InputOption::VALUE_REQUIRED, 'Fetch by extensions, e.g "openssl,mbstring"');
        $this->addOption('for-libs', 'l', InputOption::VALUE_REQUIRED, 'Fetch by libraries, e.g "libcares,openssl,onig"');
        $this->addOption('without-suggests', null, null, 'Do not fetch suggested sources when using --for-extensions');

        $this->addOption('without-suggestions', null, null, '(deprecated) Do not fetch suggested sources when using --for-extensions');

        // download command specific options
        $this->addOption('clean', null, null, 'Clean old download cache and source before fetch');
        $this->addOption('for-packages', null, InputOption::VALUE_REQUIRED, 'Fetch by packages, e.g "php,libssl,libcurl"');

        // shared downloader options (no prefix for download command)
        $this->getDefinition()->addOptions(DownloaderOptions::getConsoleOptions());
    }

    public function handle(): int
    {
        // handle --clean option
        if ($this->getOption('clean')) {
            return $this->handleClean();
        }

        $downloader = new ArtifactDownloader(DownloaderOptions::extractFromConsoleOptions($this->input->getOptions()));

        // arguments
        if ($artifacts = $this->getArgument('artifacts')) {
            $artifacts = parse_comma_list($artifacts);
            $downloader->addArtifacts($artifacts);
        }
        // for-extensions
        $packages = [];
        if ($exts = $this->getOption('for-extensions')) {
            $packages = array_map(fn ($x) => "ext-{$x}", parse_extension_list($exts));
            // when using for-extensions, also include php package
            array_unshift($packages, 'php');
            array_unshift($packages, 'php-micro');
            array_unshift($packages, 'php-embed');
            array_unshift($packages, 'php-fpm');
        }
        // for-libs / for-packages
        if ($libs = $this->getOption('for-libs')) {
            $packages = array_merge($packages, parse_comma_list($libs));
        }
        if ($libs = $this->getOption('for-packages')) {
            $packages = array_merge($packages, parse_comma_list($libs));
        }

        // resolve package dependencies and get artifacts directly
        $suggests = !($this->getOption('without-suggests') || $this->getOption('without-suggestions'));
        $resolved = DependencyResolver::resolve($packages, [], $suggests);
        foreach ($resolved as $pkg_name) {
            $pkg = PackageLoader::getPackage($pkg_name);
            if ($artifact = $pkg->getArtifact()) {
                $downloader->add($artifact);
            }
        }
        $starttime = microtime(true);
        $downloader->download();

        $endtime = microtime(true);
        $elapsed = round($endtime - $starttime);
        $this->output->writeln('');
        $this->output->writeln('<info>Download completed in ' . $elapsed . ' s.</info>');
        return static::SUCCESS;
    }

    private function handleClean(): int
    {
        logger()->warning('You are doing some operations that are not recoverable:');
        logger()->warning('- Removing directory: ' . SOURCE_PATH);
        logger()->warning('- Removing directory: ' . DOWNLOAD_PATH);
        logger()->warning('- Removing directory: ' . BUILD_ROOT_PATH);
        logger()->alert('I will remove these directories after 5 seconds!');
        sleep(5);

        if (is_dir(SOURCE_PATH)) {
            InteractiveTerm::indicateProgress('Removing: ' . SOURCE_PATH);
            FileSystem::removeDir(SOURCE_PATH);
            InteractiveTerm::finish('Removed: ' . SOURCE_PATH);
        }
        if (is_dir(DOWNLOAD_PATH)) {
            InteractiveTerm::indicateProgress('Removing: ' . DOWNLOAD_PATH);
            FileSystem::removeDir(DOWNLOAD_PATH);
            InteractiveTerm::finish('Removed: ' . DOWNLOAD_PATH);
        }
        if (is_dir(BUILD_ROOT_PATH)) {
            InteractiveTerm::indicateProgress('Removing: ' . BUILD_ROOT_PATH);
            FileSystem::removeDir(BUILD_ROOT_PATH);
            InteractiveTerm::finish('Removed: ' . BUILD_ROOT_PATH);
        }

        InteractiveTerm::notice('Clean completed.');
        return static::SUCCESS;
    }
}
