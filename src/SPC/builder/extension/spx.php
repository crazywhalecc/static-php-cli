<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('spx')]
class spx extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-spx' . ($shared ? '=shared' : '');
        if ($this->builder->getLib('zlib') !== null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/Makefile.frag',
            '@cp -r assets/web-ui/*',
            '@cp -r ' . $this->source_dir . '/assets/web-ui/*',
        );
        return true;
    }
}
