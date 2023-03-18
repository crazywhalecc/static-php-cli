<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * is a template library class for unix
 */
class freetype extends MacOSLibraryBase
{
    public const NAME = 'freetype';

    protected function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        $suggested = '';
        $suggested .= ($this->builder->getLib('libpng') instanceof MacOSLibraryBase) ? ('--with-png=' . BUILD_ROOT_PATH) : '--without-png';
        $suggested .= ' ';
        $suggested .= ($this->builder->getLib('bzip2') instanceof MacOSLibraryBase) ? ('--with-bzip2=' . BUILD_ROOT_PATH) : '--without-bzip2';
        $suggested .= ' ';
        $suggested .= ($this->builder->getLib('brotli') instanceof MacOSLibraryBase) ? ('--with-brotli=' . BUILD_ROOT_PATH) : '--without-brotli';
        $suggested .= ' ';

        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            "{$this->builder->configure_env} ./configure " .
            '--enable-static --disable-shared --without-harfbuzz ' .
            $suggested .
            '--prefix= && ' . // use prefix=/
            'make clean && ' .
            "make -j{$this->builder->concurrency} && " .
            'make install DESTDIR=' . $destdir
        );
    }
}
