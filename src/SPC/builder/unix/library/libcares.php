<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixAutoconfExecutor;

trait libcares
{
    public function patchBeforeBuild(): bool
    {
        if (!file_exists($this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h')) {
            FileSystem::createDir($this->source_dir . '/src/lib/thirdparty/apple');
            copy(ROOT_DIR . '/src/globals/extra/libcares_dnsinfo.h', $this->source_dir . '/src/lib/thirdparty/apple/dnsinfo.h');
            return true;
        }
        return false;
    }

    protected function build(): void
    {
        UnixAutoconfExecutor::create($this)->configure('--disable-tests')->make();
        $this->patchPkgconfPrefix(['libcares.pc'], PKGCONF_PATCH_PREFIX);
    }
}
