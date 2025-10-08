<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('readline')]
class readline extends Extension
{
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
        return '--with-libedit --without-readline';
    }

    public function buildUnixShared(): void
    {
        if (!file_exists(BUILD_BIN_PATH . '/php') || !file_exists(BUILD_INCLUDE_PATH . '/php/sapi/cli/cli.h')) {
            logger()->warning('CLI mode is not enabled, skipping readline build');
            return;
        }
        parent::buildUnixShared();
    }

    public function runCliCheckUnix(): void
    {
        parent::runCliCheckUnix();
        [$ret, $out] = shell()->execWithResult('printf "exit\n" | ' . BUILD_BIN_PATH . '/php -a');
        if ($ret !== 0 || !str_contains(implode("\n", $out), 'Interactive shell')) {
            throw new ValidationException("readline extension failed sanity check. Code: {$ret}, output: " . implode("\n", $out));
        }
    }
}
