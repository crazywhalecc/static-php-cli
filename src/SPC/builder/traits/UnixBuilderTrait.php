<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\exception\RuntimeException;

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
     * @throws RuntimeException
     */
    public function sanityCheck(int $build_micro_rule): void
    {
        logger()->info('running sanity check');
        if ($build_micro_rule !== BUILD_MICRO_ONLY) {
            f_exec(
                $this->set_x . ' && ' .
                SOURCE_PATH . '/php-src/sapi/cli/php -r "echo \"hello\";"',
                $output,
                $ret
            );
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new RuntimeException('cli failed sanity check');
            }
            foreach ($this->exts as $ext) {
                logger()->debug('checking ext: ' . $ext->getName());
                if (file_exists(ROOT_DIR . '/src/globals/tests/' . $ext->getName() . '.php')) {
                    f_exec(
                        $this->set_x . ' && ' . SOURCE_PATH . '/php-src/sapi/cli/php ' . ROOT_DIR . '/src/globals/tests/' . $ext->getName() . '.php',
                        $output,
                        $ret
                    );
                    if ($ret !== 0) {
                        throw new RuntimeException('extension ' . $ext->getName() . ' failed sanity check');
                    }
                }
            }
        }
        if ($build_micro_rule !== BUILD_MICRO_NONE) {
            if (file_exists(SOURCE_PATH . '/hello.exe')) {
                @unlink(SOURCE_PATH . '/hello.exe');
            }
            file_put_contents(
                SOURCE_PATH . '/hello.exe',
                file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx') .
                '<?php echo "hello";'
            );
            chmod(SOURCE_PATH . '/hello.exe', 0755);
            f_exec(SOURCE_PATH . '/hello.exe', $output2, $ret);
            if ($ret !== 0 || trim($out = implode('', $output2)) !== 'hello') {
                throw new RuntimeException('micro failed sanity check, ret[' . $ret . '], out[' . ($out ?? 'NULL') . ']');
            }
        }
    }
}
