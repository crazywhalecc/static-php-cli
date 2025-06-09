<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\util\executor\UnixAutoconfExecutor;

trait readline
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->configure(
                '--with-curses',
                '--enable-multibyte=yes',
            )
            ->make();
        $this->patchPkgconfPrefix(['readline.pc']);
    }
}
