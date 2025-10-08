<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCTarget;

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

    public function patchBeforeMake(): bool
    {
        if (SPCTarget::getTargetOS() === 'Linux' && SPCTarget::isStatic() && $this->builder->getOption('build-cli')) {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/readline/readline_cli.c',
                "/*\n#ifdef COMPILE_DL_READLINE",
                "#ifdef COMPILE_DL_READLINE\n/*"
            );
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/ext/readline/readline_cli.c',
                '/\/\*#else\s+?#define GET_SHELL_CB\(cb\) \(cb\) = php_cli_get_shell_callbacks\(\)\s+#endif\*\//s',
                "#else\n#define GET_SHELL_CB(cb) (cb) = php_cli_get_shell_callbacks()\n#endif"
            );
        } else {
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/readline/readline_cli.c',
                "#ifdef COMPILE_DL_READLINE\n/*",
                "/*\n#ifdef COMPILE_DL_READLINE"
            );
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/ext/readline/readline_cli.c',
                '/#else\s+?#define GET_SHELL_CB\(cb\) \(cb\) = php_cli_get_shell_callbacks\(\)\s+#endif/s',
                "/*#else\n#define GET_SHELL_CB(cb) (cb) = php_cli_get_shell_callbacks()\n#endif*/"
            );
        }
        return false;
    }
}
