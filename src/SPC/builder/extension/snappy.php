<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('snappy')]
class snappy extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/configure',
            '/-lsnappy/',
            $this->getLibFilesString() . ($this->builder instanceof MacOSBuilder ? ' -lc++' : ' -lstdc++')
        );
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-snappy --with-snappy-includedir="' . BUILD_ROOT_PATH . '"';
    }
}
