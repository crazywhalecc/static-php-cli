<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class curl extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\curl;

    public const NAME = 'curl';

    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true, bool $include_self = true): string
    {
        $libs = parent::getStaticLibFiles($style, $recursive, $include_self);
        if ($this->builder->getLib('openssl')) {
            $libs .= ' -ldl -lpthread';
        }
        return $libs;
    }
}
