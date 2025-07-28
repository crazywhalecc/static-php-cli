<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait unixodbc
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-debug',
                '--disable-dependency-tracking',
                "--with-libiconv-prefix={$this->getBuildRootPath()}",
                '--with-included-ltdl',
                '--enable-gui=no',
            )
            ->make();
        $this->patchPkgconfPrefix(['odbc.pc', 'odbccr.pc', 'odbcinst.pc']);
        $this->patchLaDependencyPrefix();
    }
}
