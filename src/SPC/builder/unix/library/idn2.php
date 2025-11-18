<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait idn2
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--disable-nls',
                '--disable-doc',
                '--enable-year2038',
                '--disable-rpath'
            )
            ->optionalLib('libiconv', "--with-libiconv-prefix={$this->getBuildRootPath()}")
            ->optionalLib('libunistring', "--with-libunistring-prefix={$this->getBuildRootPath()}")
            ->optionalLib('gettext', "--with-libnintl-prefix={$this->getBuildRootPath()}")
            ->make();
        $this->patchPkgconfPrefix(['libidn2.pc']);
        $this->patchLaDependencyPrefix();
    }
}
