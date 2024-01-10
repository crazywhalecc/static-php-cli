<?php

declare(strict_types=1);

namespace SPC\builder\unix;

use SPC\builder\BuilderBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\store\SourceExtractor;
use SPC\util\DependencyUtil;

abstract class UnixBuilderBase extends BuilderBase
{
    /** @var string cflags */
    public string $arch_c_flags;

    /** @var string C++ flags */
    public string $arch_cxx_flags;

    /** @var string cmake toolchain file */
    public string $cmake_toolchain_file;

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function getAllStaticLibFiles(): array
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                $libs[] = $dep;
            }
            $libs[] = $lib;
        }

        $libFiles = [];
        $libNames = [];
        // merge libs
        foreach ($libs as $lib) {
            if (!in_array($lib::NAME, $libNames, true)) {
                $libNames[] = $lib::NAME;
                array_unshift($libFiles, ...$lib->getStaticLibs());
            }
        }
        return array_map(fn ($x) => realpath(BUILD_LIB_PATH . "/{$x}"), $libFiles);
    }

    /**
     * Return generic cmake options when configuring cmake projects
     */
    public function makeCmakeArgs(): string
    {
        $extra = $this instanceof LinuxBuilder ? '-DCMAKE_C_COMPILER=' . getenv('CC') . ' ' : '';
        return $extra .
            '-DCMAKE_BUILD_TYPE=Release ' .
            '-DCMAKE_INSTALL_PREFIX=/ ' .
            '-DCMAKE_INSTALL_BINDIR=/bin ' .
            '-DCMAKE_INSTALL_LIBDIR=/lib ' .
            '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
            "-DCMAKE_TOOLCHAIN_FILE={$this->cmake_toolchain_file}";
    }

    /**
     * Generate configure flags
     */
    public function makeAutoconfFlags(int $flag = AUTOCONF_ALL): string
    {
        $extra = '';
        // TODO: add auto pkg-config support
        if (($flag & AUTOCONF_LIBS) === AUTOCONF_LIBS) {
            $extra .= 'LIBS="' . BUILD_LIB_PATH . '" ';
        }
        if (($flag & AUTOCONF_CFLAGS) === AUTOCONF_CFLAGS) {
            $extra .= 'CFLAGS="-I' . BUILD_INCLUDE_PATH . '" ';
        }
        if (($flag & AUTOCONF_CPPFLAGS) === AUTOCONF_CPPFLAGS) {
            $extra .= 'CPPFLAGS="-I' . BUILD_INCLUDE_PATH . '" ';
        }
        if (($flag & AUTOCONF_LDFLAGS) === AUTOCONF_LDFLAGS) {
            $extra .= 'LDFLAGS="-L' . BUILD_LIB_PATH . '" ';
        }
        return $extra;
    }

    public function buildLibs(array $sorted_libraries): void
    {
        // search all supported libs
        $support_lib_list = [];
        $classes = FileSystem::getClassesPsr4(
            ROOT_DIR . '/src/SPC/builder/' . osfamily2dir() . '/library',
            'SPC\\builder\\' . osfamily2dir() . '\\library'
        );
        foreach ($classes as $class) {
            if (defined($class . '::NAME') && $class::NAME !== 'unknown' && Config::getLib($class::NAME) !== null) {
                $support_lib_list[$class::NAME] = $class;
            }
        }

        // if no libs specified, compile all supported libs
        if ($sorted_libraries === [] && $this->isLibsOnly()) {
            $libraries = array_keys($support_lib_list);
            $sorted_libraries = DependencyUtil::getLibsByDeps($libraries);
        }

        // pkg-config must be compiled first, whether it is specified or not
        if (!in_array('pkg-config', $sorted_libraries)) {
            array_unshift($sorted_libraries, 'pkg-config');
        }

        // add lib object for builder
        foreach ($sorted_libraries as $library) {
            // if some libs are not supported (but in config "lib.json", throw exception)
            if (!isset($support_lib_list[$library])) {
                throw new WrongUsageException('library [' . $library . '] is in the lib.json list but not supported to compile, but in the future I will support it!');
            }
            $lib = new ($support_lib_list[$library])($this);
            $this->addLib($lib);
        }

        // calculate and check dependencies
        foreach ($this->libs as $lib) {
            $lib->calcDependency();
        }

        // patch point
        $this->emitPatchPoint('before-libs-extract');

        // extract sources
        SourceExtractor::initSource(libs: $sorted_libraries);

        $this->emitPatchPoint('after-libs-extract');

        // build all libs
        foreach ($this->libs as $lib) {
            match ($lib->tryBuild($this->getOption('rebuild', false))) {
                BUILD_STATUS_OK => logger()->info('lib [' . $lib::NAME . '] build success'),
                BUILD_STATUS_ALREADY => logger()->notice('lib [' . $lib::NAME . '] already built'),
                BUILD_STATUS_FAILED => logger()->error('lib [' . $lib::NAME . '] build failed'),
                default => logger()->warning('lib [' . $lib::NAME . '] build status unknown'),
            };
        }
    }

    /**
     * Sanity check after build complete
     *
     * @throws RuntimeException
     */
    protected function sanityCheck(int $build_target): void
    {
        // sanity check for php-cli
        if (($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI) {
            logger()->info('running cli sanity check');
            [$ret, $output] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -r "echo \"hello\";"');
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new RuntimeException('cli failed sanity check');
            }

            foreach ($this->exts as $ext) {
                logger()->debug('testing ext: ' . $ext->getName());
                $ext->runCliCheckUnix();
            }
        }

        // sanity check for phpmicro
        if (($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO) {
            if (file_exists(SOURCE_PATH . '/hello.exe')) {
                @unlink(SOURCE_PATH . '/hello.exe');
            }
            file_put_contents(
                SOURCE_PATH . '/hello.exe',
                file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx') .
                '<?php echo "hello";'
            );
            chmod(SOURCE_PATH . '/hello.exe', 0755);
            [$ret, $output2] = shell()->execWithResult(SOURCE_PATH . '/hello.exe');
            if ($ret !== 0 || trim($out = implode('', $output2)) !== 'hello') {
                throw new RuntimeException('micro failed sanity check, ret[' . $ret . '], out[' . ($out ?? 'NULL') . ']');
            }
        }
    }

    /**
     * 将编译好的二进制文件发布到 buildroot
     *
     * @param  int                 $type 发布类型
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function deployBinary(int $type): bool
    {
        $src = match ($type) {
            BUILD_TARGET_CLI => SOURCE_PATH . '/php-src/sapi/cli/php',
            BUILD_TARGET_MICRO => SOURCE_PATH . '/php-src/sapi/micro/micro.sfx',
            BUILD_TARGET_FPM => SOURCE_PATH . '/php-src/sapi/fpm/php-fpm',
            default => throw new RuntimeException('Deployment does not accept type ' . $type),
        };
        logger()->info('Deploying ' . $this->getBuildTypeName($type) . ' file');
        FileSystem::createDir(BUILD_ROOT_PATH . '/bin');
        shell()->exec('cp ' . escapeshellarg($src) . ' ' . escapeshellarg(BUILD_ROOT_PATH . '/bin/'));
        return true;
    }

    /**
     * Run php clean
     *
     * @throws RuntimeException
     */
    protected function cleanMake(): void
    {
        logger()->info('cleaning up');
        shell()->cd(SOURCE_PATH . '/php-src')->exec('make clean');
    }
}
