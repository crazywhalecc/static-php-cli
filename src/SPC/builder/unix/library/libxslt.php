<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\executor\UnixAutoconfExecutor;

trait libxslt
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        $static_libs = $this instanceof LinuxLibraryBase ? $this->getStaticLibFiles(include_self: false) : '';
        $cpp = $this instanceof MacOSLibraryBase ? '-lc++' : '-lstdc++';
        $ac = UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => "-I{$this->getIncludeDir()}",
                'LDFLAGS' => "-L{$this->getLibDir()}",
                'LIBS' => "{$static_libs} {$cpp}",
            ])
            ->addConfigureArgs(
                '--without-python',
                '--without-crypto',
                '--without-debug',
                '--without-debugger',
                "--with-libxml-prefix={$this->getBuildRootPath()}",
            );
        if (getenv('SPC_LINUX_DEFAULT_LD_LIBRARY_PATH') && getenv('SPC_LINUX_DEFAULT_LIBRARY_PATH')) {
            $ac->appendEnv([
                'LD_LIBRARY_PATH' => getenv('SPC_LINUX_DEFAULT_LD_LIBRARY_PATH'),
                'LIBRARY_PATH' => getenv('SPC_LINUX_DEFAULT_LIBRARY_PATH'),
            ]);
        }
        $ac->configure()->make();

        $this->patchPkgconfPrefix(['libexslt.pc', 'libxslt.pc']);
        $this->patchLaDependencyPrefix();
        shell()->cd(BUILD_LIB_PATH)
            ->exec("ar -t libxslt.a | grep '\\.a$' | xargs -n1 ar d libxslt.a")
            ->exec("ar -t libexslt.a | grep '\\.a$' | xargs -n1 ar d libexslt.a");
    }
}
