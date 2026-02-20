<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait xz
{
    public function build(): void
    {
        $make = UnixAutoconfExecutor::create($this);
        if (!getenv('SPC_LINK_STATIC')) {
            // liblzma can only build one of static or shared at a time
            $make
                ->removeConfigureArgs('--enable-static')
                ->addConfigureArgs('--disable-static');
        }
        $make
            ->configure(
                '--disable-scripts',
                '--disable-doc',
                '--with-libiconv',
                '--bindir=/tmp/xz', // xz binary will corrupt `tar` command, that's really strange.
            )
            ->make();
        $this->patchPkgconfPrefix(['liblzma.pc']);
        $this->patchLaDependencyPrefix();
    }
}
