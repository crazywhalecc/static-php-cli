<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\CustomExt;

#[CustomExt('simdjson')]
class simdjson extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        $php_ver = $this->builder->getPHPVersionID();
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/ext/simdjson/config.m4',
            '/php_version=(`.*`)$/m',
            'php_version=' . $php_ver
        );
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/simdjson/config.m4',
            'if test -z "$PHP_CONFIG"; then',
            'if false; then'
        );
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/simdjson/config.w32',
            "'yes',",
            'PHP_SIMDJSON_SHARED,'
        );
        return true;
    }

    public function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class) {
            $extra = getenv('SPC_COMPILER_EXTRA');
            if (!str_contains((string) $extra, '-lstdc++')) {
                f_putenv('SPC_COMPILER_EXTRA=' . clean_spaces($extra . ' -lstdc++'));
            }
            $env['CFLAGS'] .= ' -Xclang -target-feature -Xclang +evex512';
            $env['CXXFLAGS'] .= ' -Xclang -target-feature -Xclang +evex512';
        }
        return $env;
    }
}
