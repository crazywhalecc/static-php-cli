<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\FileSystemException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('readline')]
class readline extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/configure',
            '/-lncurses/',
            $this->getLibFilesString()
        );
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--without-libedit --with-readline=' . BUILD_ROOT_PATH;
    }

    public function buildUnixShared(): void
    {
        if (!file_exists(BUILD_BIN_PATH . '/php') || !file_exists(BUILD_INCLUDE_PATH . '/php/sapi/cli/cli.h')) {
            logger()->warning('CLI mode is not enabled, skipping readline build');
            return;
        }
        parent::buildUnixShared();
    }
}
