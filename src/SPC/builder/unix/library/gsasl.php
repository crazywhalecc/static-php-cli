<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait gsasl
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->optionalLib('idn2', ...ac_with_args('libidn2', true))
            ->optionalLib('krb5', ...ac_with_args('gssapi', true))
            ->configure(
                '--disable-nls',
                '--disable-rpath',
                '--disable-doc',
            )
            ->make();
        $this->patchPkgconfPrefix(['libgsasl.pc']);
        $this->patchLaDependencyPrefix();
    }
}
