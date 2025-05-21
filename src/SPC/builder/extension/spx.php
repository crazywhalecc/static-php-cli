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
        $arg = '--enable-spx';
        if ($this->builder->getExt('zlib') === null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    public function patchBeforeSharedConfigure(): bool
    {
        FileSystem::replaceFileStr($this->source_dir . '/config.m4', 'PHP_ARG_ENABLE(SPX-DEV,', 'PHP_ARG_ENABLE(spx-dev,');
        FileSystem::replaceFileStr($this->source_dir . '/config.m4', 'PHP_ARG_ENABLE(SPX,', 'PHP_ARG_ENABLE(spx,');
        return true;
    }
}
