<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

trait UnixBuilderTrait
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
                $ext->runCliCheck();
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
}
