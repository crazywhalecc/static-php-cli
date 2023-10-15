<?php

declare(strict_types=1);

namespace SPC\builder\freebsd\library;

class curl extends BSDLibraryBase
{
    use \SPC\builder\unix\library\curl;

    public const NAME = 'curl';

    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true): string
    {
        $libs = parent::getStaticLibFiles($style, $recursive);
        if ($this->builder->getLib('openssl')) {
            $this->builder->setOption('extra-libs', $this->builder->getOption('extra-libs') . ' /usr/lib/libpthread.a /usr/lib/libdl.a');
        }
        return $libs;
    }
}
