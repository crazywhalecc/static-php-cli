<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\builder\BuilderProvider;
use SPC\command\BuildCommand;
use SPC\exception\ExceptionHandler;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('dev:pack-lib', 'Build and pack library as pre-built release')]
class PackLibCommand extends BuildCommand
{
    public function configure(): void
    {
        $this->addArgument('library', InputArgument::REQUIRED, 'The library will be compiled');
    }

    public function handle(): int
    {
        try {
            $lib_name = $this->getArgument('library');
            $builder = BuilderProvider::makeBuilderByInput($this->input);
            $builder->setLibsOnly();
            $libraries = DependencyUtil::getLibs([$lib_name]);
            logger()->info('Building libraries: ' . implode(',', $libraries));
            sleep(2);

            FileSystem::createDir(WORKING_DIR . '/dist');

            $builder->proveLibs($libraries);
            $builder->validateLibsAndExts();
            foreach ($builder->getLibs() as $lib) {
                if ($lib->getName() !== $lib_name) {
                    // other dependencies: install or build, both ok
                    $lib->setup();
                } else {
                    // Get lock info
                    $lock = json_decode(file_get_contents(DOWNLOAD_PATH . '/.lock.json'), true) ?? [];
                    $source = Config::getLib($lib->getName(), 'source');
                    if (!isset($lock[$source]) || ($lock[$source]['lock_as'] ?? SPC_LOCK_SOURCE) === SPC_LOCK_PRE_BUILT) {
                        logger()->critical("The library {$lib->getName()} is downloaded as pre-built, we need to build it instead of installing pre-built.");
                        return static::FAILURE;
                    }
                    // Before build: load buildroot/ directory
                    $before_buildroot = FileSystem::scanDirFiles(BUILD_ROOT_PATH, relative: true);
                    // build
                    $lib->tryBuild(true);
                    // do something like patching pkg-conf files.
                    $lib->beforePack();
                    // After build: load buildroot/ directory, and calculate increase files
                    $after_buildroot = FileSystem::scanDirFiles(BUILD_ROOT_PATH, relative: true);
                    $increase_files = array_diff($after_buildroot, $before_buildroot);
                    // every file mapped with BUILD_ROOT_PATH
                    // get BUILD_ROOT_PATH last dir part
                    $buildroot_part = basename(BUILD_ROOT_PATH);
                    $increase_files = array_map(fn ($file) => $buildroot_part . '/' . $file, $increase_files);
                    // write list to packlib_files.txt
                    FileSystem::writeFile(WORKING_DIR . '/packlib_files.txt', implode("\n", $increase_files));
                    // pack
                    $filename = WORKING_DIR . '/dist/' . $lib->getName() . '-' . arch2gnu(php_uname('m')) . '-' . strtolower(PHP_OS_FAMILY) . '.' . Config::getPreBuilt('suffix');
                    f_passthru('tar -czf ' . $filename . ' -T ' . WORKING_DIR . '/packlib_files.txt');
                    logger()->info('Pack library ' . $lib->getName() . ' to ' . $filename . ' complete.');
                }
            }

            $time = round(microtime(true) - START_TIME, 3);
            logger()->info('Build libs complete, used ' . $time . ' s !');
            return static::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->getOption('debug')) {
                ExceptionHandler::getInstance()->handle($e);
            } else {
                logger()->critical('Build failed with ' . get_class($e) . ': ' . $e->getMessage());
                logger()->critical('Please check with --debug option to see more details.');
            }
            return static::FAILURE;
        }
    }
}
