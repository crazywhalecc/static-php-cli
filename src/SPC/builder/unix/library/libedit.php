<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait libedit
{
    protected function build(): void
    {
        $make = UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-D__STDC_ISO_10646__=201103L'])
            ->configure();

        foreach (['strlcpy', 'strlcat', 'fgetln'] as $symbol) {
            $usymbol = strtoupper($symbol);
            FileSystem::replaceFileLineContainsString(
                $this->source_dir . '/config.h',
                "/* #undef HAVE_{$usymbol} */",
                "/* #undef HAVE_{$usymbol} */\n#define {$symbol} libedit_{$symbol}"
            );
        }

        $make->make();
        $this->patchPkgconfPrefix(['libedit.pc']);
    }
}
