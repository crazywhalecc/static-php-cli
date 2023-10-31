<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

class libffi extends LinuxLibraryBase
{
    public const NAME = 'libffi';

    /**
     * @throws RuntimeException
     */
    public function build(): void
    {
        [$lib, , $destdir] = SEPARATED_PATH;

        /*$env = $this->builder->pkgconf_env . ' CFLAGS="' . $this->builder->arch_c_flags . '"';

        $env .= match ($this->builder->libc) {
            'musl_wrapper' => " CC='{$this->builder->getOption('cc')} --static -idirafter " . BUILD_INCLUDE_PATH .
                ($this->builder->getOption('arch') === php_uname('m') ? '-idirafter /usr/include/ ' : '') .
                "-idirafter /usr/include/{$this->builder->getOption('arch')}-linux-gnu/'",
            'musl', 'glibc' => " CC='{$this->builder->getOption('cc')}'",
            default => throw new RuntimeException('unsupported libc: ' . $this->builder->libc),
        };*/

        shell()->cd($this->source_dir)
            ->exec(
                './configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->builder->getOption('arch')}-unknown-linux " .
                "--target={$this->builder->getOption('arch')}-unknown-linux " .
                '--prefix= ' . // use prefix=/
                "--libdir={$lib}"
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");

        if (is_file(BUILD_ROOT_PATH . '/lib64/libffi.a')) {
            copy(BUILD_ROOT_PATH . '/lib64/libffi.a', BUILD_ROOT_PATH . '/lib/libffi.a');
            unlink(BUILD_ROOT_PATH . '/lib64/libffi.a');
        }
        $this->patchPkgconfPrefix(['libffi.pc']);
    }
}
