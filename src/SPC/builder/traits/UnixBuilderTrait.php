<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait UnixBuilderTrait
{
    /** @var string 设置的命令前缀，设置为 set -x 可以在终端打印命令 */
    public string $set_x = 'set -x';

    /** @var string C 编译器命令 */
    public string $cc;

    /** @var string C++ 编译器命令 */
    public string $cxx;

    /** @var string cflags 参数 */
    public string $arch_c_flags;

    /** @var string C++ flags 参数 */
    public string $arch_cxx_flags;

    /** @var string cmake toolchain file */
    public string $cmake_toolchain_file;

    /** @var string configure 环境依赖的变量 */
    public string $configure_env;

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
    public function sanityCheck(int $build_target): void
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
                [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php --ri "' . $ext->getDistName() . '"', false);
                if ($ret !== 0) {
                    throw new RuntimeException('extension ' . $ext->getName() . ' failed compile check');
                }
                if (file_exists(ROOT_DIR . '/src/globals/tests/' . $ext->getName() . '.php')) {
                    [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php ' . ROOT_DIR . '/src/globals/tests/' . $ext->getName() . '.php');
                    if ($ret !== 0) {
                        throw new RuntimeException('extension ' . $ext->getName() . ' failed sanity check');
                    }
                }
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
    public function deployBinary(int $type): bool
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
     * 清理编译好的文件
     *
     * @throws RuntimeException
     */
    public function cleanMake(): void
    {
        logger()->info('cleaning up');
        shell()->cd(SOURCE_PATH . '/php-src')->exec('make clean');
    }

    /**
     * Return generic cmake options when configuring cmake projects
     */
    public function makeCmakeArgs(): string
    {
        [$lib, $include] = SEPARATED_PATH;
        $extra = $this instanceof LinuxBuilder ? '-DCMAKE_C_COMPILER=' . $this->cc . ' ' : '';
        return $extra . '-DCMAKE_BUILD_TYPE=Release ' .
            '-DCMAKE_INSTALL_PREFIX=/ ' .
            "-DCMAKE_INSTALL_LIBDIR={$lib} " .
            "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
            "-DCMAKE_TOOLCHAIN_FILE={$this->cmake_toolchain_file}";
    }
}
