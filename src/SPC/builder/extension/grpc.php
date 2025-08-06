<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\ValidationException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCConfigUtil;
use SPC\util\SPCTarget;

#[CustomExt('grpc')]
class grpc extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder instanceof WindowsBuilder) {
            throw new ValidationException('grpc extension does not support windows yet');
        }
        if (file_exists(SOURCE_PATH . '/php-src/ext/grpc')) {
            return false;
        }
        // soft link to the grpc source code
        if (is_dir($this->source_dir . '/src/php/ext/grpc')) {
            shell()->exec('ln -s ' . $this->source_dir . '/src/php/ext/grpc ' . SOURCE_PATH . '/php-src/ext/grpc');
        } else {
            throw new ValidationException('Cannot find grpc source code in ' . $this->source_dir . '/src/php/ext/grpc');
        }
        if (SPCTarget::getTargetOS() === 'Darwin') {
            FileSystem::replaceFileRegex(
                SOURCE_PATH . '/php-src/ext/grpc/config.m4',
                '/GRPC_LIBDIR=.*$/m',
                'GRPC_LIBDIR=' . BUILD_LIB_PATH . "\n" . 'LDFLAGS="$LDFLAGS -framework CoreFoundation"'
            );
        }
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        $util = new SPCConfigUtil($this->builder, ['libs_only_deps' => true]);
        $config = $util->config(['grpc']);
        $libs = $config['libs'];
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lgrpc', $libs);
        return true;
    }

    public function patchBeforeMake(): bool
    {
        parent::patchBeforeMake();
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }
}
