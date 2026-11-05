<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Runtime\SystemTarget;
use Symfony\Component\Console\Input\InputOption;

final class SharedLibraryUtil
{
    private static array $originalEnvironment = [];

    public static function options(): array
    {
        return [
            new InputOption('with-shared-libs', null, null, 'Build and link shared dependency libraries (Linux only)'),
            new InputOption('with-shared-libdir', null, InputOption::VALUE_REQUIRED, 'Installed runtime library directory', '/usr/lib/php-zts/lib'),
            new InputOption('use-existing-libraries', null, null, 'Require and reuse a verified shared-library SDK'),
            new InputOption('reuse-installed-library-dependencies', null, null, 'Reuse verified unselected library dependencies'),
        ];
    }

    public static function enabled(): bool
    {
        return (bool) ApplicationContext::tryGet(PackageBuilder::class)?->getOption('with-shared-libs', false);
    }

    public static function isManaged(Package $package): bool
    {
        return $package instanceof LibraryPackage && ($package->getType() === 'library'
            || PackageConfig::get($package->getName(), 'static-libs', []) !== []
            || PackageConfig::get($package->getName(), 'shared-libs', []) !== []);
    }

    public static function buildsShared(LibraryPackage $package): bool
    {
        return self::enabled() && SystemTarget::getTargetOS() === 'Linux' && self::isManaged($package)
            && !PackageConfig::get($package->getName(), 'static-only', false);
    }

    public static function libraryFiles(string $name): array
    {
        $static = PackageConfig::get($name, 'static-libs', []);
        if (!self::enabled() || PackageConfig::get($name, 'static-only', false)) {
            return $static;
        }
        return PackageConfig::get($name, 'shared-libs', array_map(
            static fn (string $file): string => preg_replace('/\.a$/D', '.so', $file),
            $static
        ));
    }

    public static function runtimeDirectory(): string
    {
        $directory = ApplicationContext::tryGet(PackageBuilder::class)?->getOption('with-shared-libdir', '/usr/lib/php-zts/lib');
        if (!is_string($directory) || !preg_match('~^/(?:[A-Za-z0-9_+.-]+/)*[A-Za-z0-9_.+-]+$~D', $directory)
            || in_array('..', explode('/', $directory), true) || in_array('.', explode('/', $directory), true)) {
            throw new WrongUsageException('Invalid shared-library runtime directory');
        }
        return $directory;
    }

    public static function resetEnvironment(): void
    {
        foreach (self::$originalEnvironment as $key => $value) {
            putenv($key . ($value === false ? '' : '=' . $value));
        }
        self::$originalEnvironment = [];
    }

    public static function configureEnvironment(): void
    {
        if (!self::enabled()) {
            return;
        }
        if (SystemTarget::getTargetOS() !== 'Linux') {
            throw new WrongUsageException('Shared dependency libraries currently require a Linux target');
        }
        foreach (['SPC_DEFAULT_CFLAGS', 'SPC_DEFAULT_CXXFLAGS', 'SPC_DEFAULT_LDFLAGS', 'CFLAGS', 'CXXFLAGS', 'LDFLAGS', 'LIBS', 'PKG_CONFIG', 'LD_LIBRARY_PATH', 'SPC_LD_LIBRARY_PATH'] as $key) {
            if (!array_key_exists($key, self::$originalEnvironment)) {
                self::$originalEnvironment[$key] = getenv($key);
            }
        }
        foreach (['SPC_DEFAULT_CFLAGS', 'SPC_DEFAULT_CXXFLAGS', 'SPC_DEFAULT_LDFLAGS', 'CFLAGS', 'CXXFLAGS', 'LDFLAGS', 'LIBS', 'PKG_CONFIG'] as $key) {
            if (getenv($key) !== false) {
                putenv($key . '=' . self::withoutStaticFlags(getenv($key)));
            }
        }
        $path = BUILD_LIB_PATH . (getenv('LD_LIBRARY_PATH') ? ':' . getenv('LD_LIBRARY_PATH') : '');
        putenv('LD_LIBRARY_PATH=' . $path);
        putenv('SPC_LD_LIBRARY_PATH=' . $path);
    }

    public static function withoutStaticFlags(string $flags): string
    {
        $flags = preg_replace('/(?<!\S)(?:-static(?:-pie|-libgcc|-libstdc\+\+)?|--static|-Wl,-Bstatic)(?!\S)/', '', $flags);
        return trim(preg_replace('/[ \t]+/', ' ', $flags));
    }

    public static function ldflags(): string
    {
        return self::enabled() ? '-Wl,-rpath,' . self::runtimeDirectory() . ' -Wl,-rpath-link,' . BUILD_LIB_PATH : '';
    }

    public static function buildEnvironment(): array
    {
        if (!self::enabled()) {
            return [];
        }
        return ['LD_LIBRARY_PATH' => BUILD_LIB_PATH . (getenv('LD_LIBRARY_PATH') ? ':' . getenv('LD_LIBRARY_PATH') : ''),
            'LDFLAGS' => self::ldflags()];
    }

    public static function linkArchive(LibraryPackage $package, string $archive): void
    {
        if (!self::buildsShared($package)) {
            return;
        }
        $name = preg_replace('/\.a$/D', '.so', basename($archive));
        $soname = $name . '.1';
        $compiler = \StaticPHP\Config\PackageConfig::get($package->getName(), 'lang', 'c') === 'cpp'
            ? (getenv('CXX') ?: 'c++') : (getenv('CC') ?: 'cc');
        $config = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true])->getPackageDepsConfig(
            $package->getName(),
            array_keys($package->getInstaller()->getResolvedPackages())
        );
        shell()->cd(BUILD_LIB_PATH)->initializeEnv($package)->exec($compiler . ' -shared -Wl,-soname,' . $soname
            . ' -o ' . escapeshellarg($soname) . ' -Wl,--whole-archive ' . escapeshellarg($archive)
            . ' -Wl,--no-whole-archive ' . $config['ldflags'] . ' ' . $config['libs']);
        if (is_link(BUILD_LIB_PATH . '/' . $name)) {
            unlink(BUILD_LIB_PATH . '/' . $name);
        }
        if (!symlink($soname, BUILD_LIB_PATH . '/' . $name)) {
            throw new WrongUsageException('Cannot install shared library linker name: ' . $name);
        }
    }

    public static function normalizeLibraryDirectory(): void
    {
        if (!self::enabled()) {
            return;
        }
        $alternate = BUILD_ROOT_PATH . '/lib64';
        if (!is_dir($alternate) || is_link($alternate)) {
            return;
        }
        $move = static function (string $from, string $to) use (&$move): void {
            FileSystem::createDir($to);
            foreach (scandir($from) as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $source = $from . '/' . $name;
                $destination = $to . '/' . $name;
                if (is_dir($source) && !is_link($source)) {
                    $move($source, $destination);
                } elseif (file_exists($destination) || is_link($destination)) {
                    throw new WrongUsageException('Conflicting lib/lib64 output: ' . $name);
                } elseif (!rename($source, $destination)) {
                    throw new WrongUsageException('Cannot normalize library output: ' . $source);
                }
            }
            rmdir($from);
        };
        $move($alternate, BUILD_LIB_PATH);
    }

    public static function removeLibtoolArchives(): void
    {
        if (!self::enabled() || !is_dir(BUILD_LIB_PATH)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BUILD_LIB_PATH, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (str_ends_with($file->getFilename(), '.la')) {
                unlink($file->getPathname());
            }
        }
    }
}
