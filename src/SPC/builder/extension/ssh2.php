<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('ssh2')]
class ssh2 extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/configure',
            '/-lssh2/',
            $this->getLibFilesString()
        );
        return true;
    }
}
