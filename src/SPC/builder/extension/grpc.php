<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\windows\WindowsBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCTarget;

#[CustomExt('grpc')]
class grpc extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder instanceof WindowsBuilder) {
            throw new \RuntimeException('grpc extension does not support windows yet');
        }
        if (file_exists(SOURCE_PATH . '/php-src/ext/grpc')) {
            return false;
        }
        // soft link to the grpc source code
        if (is_dir($this->source_dir . '/src/php/ext/grpc')) {
            shell()->exec('ln -s ' . $this->source_dir . '/src/php/ext/grpc ' . SOURCE_PATH . '/php-src/ext/grpc');
        } else {
            throw new \RuntimeException('Cannot find grpc source code');
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
        $libs = join(' ', $this->getLibraries());
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lgrpc', $libs);
        return true;
    }

    public function patchBeforeMake(): bool
    {
        $extra_libs = trim(getenv('SPC_EXTRA_LIBS'));
        $libs = array_map(function (string $lib) {
            if (str_starts_with($lib, '-l')) {
                $staticLib = substr($lib, 2);
                $staticLib = BUILD_LIB_PATH . '/lib' . $staticLib . '.a';
                if (file_exists($staticLib)) {
                    return $staticLib;
                }
            }
            return $lib;
        }, $this->getLibraries());
        $extra_libs = str_replace(BUILD_LIB_PATH . '/libgrpc.a', join(' ', $libs), $extra_libs);
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }

    private function getLibraries(): array
    {
        $libs = shell()->execWithResult('$PKG_CONFIG --libs --static grpc')[1][0];
        $filtered = preg_replace('/-L\S+/', '', $libs);
        $filtered = preg_replace('/(?:\S*\/)?lib([a-zA-Z0-9_+-]+)\.a\b/', '-l$1', $filtered);
        $out = preg_replace('/\s+/', ' ', $filtered);
        return explode(' ', trim($out));
    }
}
