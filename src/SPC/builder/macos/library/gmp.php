<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * gmp is a template library class for unix
 */
class gmp extends MacOSLibraryBase
{
    public const NAME = 'gmp';

    protected function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            "{$this->builder->configure_env} ./configure " .
            '--enable-static --disable-shared ' .
            '--prefix= && ' . // use prefix=/
            'make clean && ' .
            "make -j{$this->builder->concurrency} && " .
            'make install DESTDIR=' . $destdir
        );
    }
}
