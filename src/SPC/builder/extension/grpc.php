<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\windows\WindowsBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\GlobalEnvManager;

#[CustomExt('grpc')]
class grpc extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // soft link to the grpc source code
        if ($this->builder instanceof WindowsBuilder) {
            // not support windows yet
            throw new \RuntimeException('grpc extension does not support windows yet');
        }
        if (!is_link(SOURCE_PATH . '/php-src/ext/grpc')) {
            if (is_dir($this->builder->getLib('grpc')->getSourceDir() . '/src/php/ext/grpc')) {
                shell()->exec('ln -s ' . $this->builder->getLib('grpc')->getSourceDir() . '/src/php/ext/grpc ' . SOURCE_PATH . '/php-src/ext/grpc');
            } elseif (is_dir(BUILD_ROOT_PATH . '/grpc_php_ext_src')) {
                shell()->exec('ln -s ' . BUILD_ROOT_PATH . '/grpc_php_ext_src ' . SOURCE_PATH . '/php-src/ext/grpc');
            } else {
                throw new \RuntimeException('Cannot find grpc source code');
            }
            $macos = $this->builder instanceof MacOSBuilder ? "\n" . '  LDFLAGS="$LDFLAGS -framework CoreFoundation"' : '';
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/ext/grpc/config.m4', '/GRPC_LIBDIR=.*$/m', 'GRPC_LIBDIR=' . BUILD_LIB_PATH . $macos);
            FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/ext/grpc/config.m4', '/SEARCH_PATH=.*$/m', 'SEARCH_PATH="' . BUILD_ROOT_PATH . '"');
            return true;
        }
        return false;
    }

    public function patchBeforeMake(): bool
    {
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }

    public function getUnixConfigureArg(): string
    {
        return '--enable-grpc=' . BUILD_ROOT_PATH . '/grpc GRPC_LIB_SUBDIR=' . BUILD_LIB_PATH;
    }
}
