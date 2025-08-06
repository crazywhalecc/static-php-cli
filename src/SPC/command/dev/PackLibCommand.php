<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\builder\BuilderProvider;
use SPC\builder\LibraryBase;
use SPC\command\BuildCommand;
use SPC\exception\ValidationException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\LockFile;
use SPC\util\DependencyUtil;
use SPC\util\SPCTarget;
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
        $lib_name = $this->getArgument('library');
        $builder = BuilderProvider::makeBuilderByInput($this->input);
        $builder->setLibsOnly();
        $libraries = DependencyUtil::getLibs([$lib_name]);
        logger()->info('Building libraries: ' . implode(',', $libraries));
        sleep(2);

        FileSystem::createDir(WORKING_DIR . '/dist');

        $builder->proveLibs($libraries);
        $builder->validateLibsAndExts();

        // before pack, check if the dependency tree contains lib-suggests
        foreach ($libraries as $lib) {
            if (Config::getLib($lib, 'lib-suggests', []) !== []) {
                logger()->critical("The library {$lib} has lib-suggests, packing [{$lib_name}] is not safe, abort !");
                return static::FAILURE;
            }
        }

        $origin_files = [];
        // get pack placehoder defines
        $placehoder = get_pack_replace();

        foreach ($builder->getLibs() as $lib) {
            if ($lib->getName() !== $lib_name) {
                // other dependencies: install or build, both ok
                $lib->setup();
            } else {
                // Get lock info
                $source = Config::getLib($lib->getName(), 'source');
                if (($lock = LockFile::get($source)) === null || ($lock['lock_as'] === SPC_DOWNLOAD_PRE_BUILT)) {
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

                // patch pkg-config and la files with absolute path
                foreach ($increase_files as $file) {
                    if (str_ends_with($file, '.pc') || str_ends_with($file, '.la')) {
                        $content = FileSystem::readFile(BUILD_ROOT_PATH . '/' . $file);
                        $origin_files[$file] = $content;
                        // replace relative paths with absolute paths
                        $content = str_replace(
                            array_keys($placehoder),
                            array_values($placehoder),
                            $content
                        );
                        FileSystem::writeFile(BUILD_ROOT_PATH . '/' . $file, $content);
                    }
                }

                // add .spc-extract-placeholder.json in BUILD_ROOT_PATH
                $placeholder_file = BUILD_ROOT_PATH . '/.spc-extract-placeholder.json';
                file_put_contents($placeholder_file, json_encode(array_keys($origin_files), JSON_PRETTY_PRINT));
                $increase_files[] = '.spc-extract-placeholder.json';

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
                    '{libc}' => SPCTarget::getLibc() ?? 'default',
                    '{libcver}' => SPCTarget::getLibcVersion() ?? 'default',
                ];
                // detect suffix, for proper tar option
                $tar_option = $this->getTarOptionFromSuffix(Config::getPreBuilt('match-pattern'));
                $filename = str_replace(array_keys($replace), array_values($replace), $filename);
                $filename = WORKING_DIR . '/dist/' . $filename;
                f_passthru("tar {$tar_option} {$filename} -T " . WORKING_DIR . '/packlib_files.txt');
                logger()->info('Pack library ' . $lib->getName() . ' to ' . $filename . ' complete.');

                // remove temp files
                unlink($placeholder_file);
            }
        }

        foreach ($origin_files as $file => $content) {
            // restore original files
            if (file_exists(BUILD_ROOT_PATH . '/' . $file)) {
                FileSystem::writeFile(BUILD_ROOT_PATH . '/' . $file, $content);
            }
        }

        $time = round(microtime(true) - START_TIME, 3);
        logger()->info('Build libs complete, used ' . $time . ' s !');
        return static::SUCCESS;
    }

    private function sanityCheckLib(LibraryBase $lib): void
    {
        logger()->info('Sanity check for library ' . $lib->getName());
        // config
        foreach ($lib->getStaticLibs() as $static_lib) {
            if (!file_exists(FileSystem::convertPath(BUILD_LIB_PATH . '/' . $static_lib))) {
                throw new ValidationException(
                    'Static library ' . $static_lib . ' not found in ' . BUILD_LIB_PATH,
                    validation_module: "Static library {$static_lib} existence check"
                );
            }
        }
    }

    /**
     * Get tar compress options from suffix
     *
     * @param  string $name Package file name
     * @return string Tar options for packaging libs
     */
    private function getTarOptionFromSuffix(string $name): string
    {
        if (str_ends_with($name, '.tar')) {
            return '-cf';
        }
        if (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) {
            return '-czf';
        }
        if (str_ends_with($name, '.tar.bz2') || str_ends_with($name, '.tbz2')) {
            return '-cjf';
        }
        if (str_ends_with($name, '.tar.xz') || str_ends_with($name, '.txz')) {
            return '-cJf';
        }
        if (str_ends_with($name, '.tar.lz') || str_ends_with($name, '.tlz')) {
            return '-c --lzma -f';
        }
        return '-cf';
    }
}
