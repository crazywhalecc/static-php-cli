<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait libedit
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileRegex(
            $this->source_dir . '/src/sys.h',
            '|//#define\s+strl|',
            '#define strl'
        );
        return true;
    }

    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-D__STDC_ISO_10646__=201103L'])
            ->configure()
            ->make();
        $this->patchPkgconfPrefix(['libedit.pc']);
    }
}
