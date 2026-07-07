<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait sqlite
{
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'CFLAGS' => '-DSQLITE_ENABLE_COLUMN_METADATA=1',
            ])
            ->configure()
            ->make();
        $this->patchPkgconfPrefix(['sqlite3.pc']);
    }
}
