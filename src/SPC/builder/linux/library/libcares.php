<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\store\FileSystem;

class libcares extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\libcares;

    public const NAME = 'libcares';

    public function patchBeforeBuild(): bool
    {
        if (!file_exists($this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h')) {
            FileSystem::createDir($this->source_dir . '/src/lib/thirdparty/apple');
            copy(ROOT_DIR . '/src/globals/extra/libcares_dnsinfo.h', $this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h');
            return true;
        }
        return false;
    }
}
