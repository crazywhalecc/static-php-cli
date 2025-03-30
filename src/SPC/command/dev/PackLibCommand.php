<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\builder\BuilderProvider;
use SPC\builder\LibraryBase;
use SPC\builder\linux\SystemUtil;
use SPC\command\BuildCommand;
use SPC\exception\ExceptionHandler;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
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
        $this->addOption('show-libc-ver', null, null);
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
                    if (!isset($lock[$source]) || ($lock[$source]['lock_as'] ?? SPC_DOWNLOAD_SOURCE) === SPC_DOWNLOAD_PRE_BUILT) {
                        logger()->critical("The library {$lib->getName()} is downloaded as pre-built, we need to build it instead of installing pre-built.");
                        return static::FAILURE;
                    }
                    // Before build: load buildroot/ directory
                    $before_buildroot = FileSystem::scanDirFiles(BUILD_ROOT_PATH, relative: true);
                    // build
                    $lib->tryBuild(true);
                    // do something like patching pkg-conf files.
                    $lib->beforePack();
                    // sanity check for libs (check if the libraries are built correctly)
                    $this->sanityCheckLib($lib);
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
                    $filename = Config::getPreBuilt('match-pattern');
                    $replace = [
                        '{name}' => $lib->getName(),
                        '{arch}' => arch2gnu(php_uname('m')),
                        '{os}' => strtolower(PHP_OS_FAMILY),
                        '{libc}' => getenv('SPC_LIBC') ?: 'default',
                        '{libcver}' => PHP_OS_FAMILY === 'Linux' ? (SystemUtil::getLibcVersionIfExists() ?? 'default') : 'default',
                    ];
                    $filename = str_replace(array_keys($replace), array_values($replace), $filename);
                    $filename = WORKING_DIR . '/dist/' . $filename;
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

    /**
     * @throws WrongUsageException
     * @throws RuntimeException
     * @throws FileSystemException
     */
    private function sanityCheckLib(LibraryBase $lib): void
    {
        logger()->info('Sanity check for library ' . $lib->getName());
        // config
        foreach ($lib->getStaticLibs() as $static_lib) {
            if (!file_exists(FileSystem::convertPath(BUILD_LIB_PATH . '/' . $static_lib))) {
                throw new RuntimeException('Static library ' . $static_lib . ' not found in ' . BUILD_LIB_PATH);
            }
        }
    }
}
