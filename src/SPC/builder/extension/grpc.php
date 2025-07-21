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

    public function patchBeforeConfigure(): bool
    {
        $libs = ' -l' . join(' -l', $this->getLibraries());
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lgrpc', $libs);
        return true;
    }

    public function patchBeforeMake(): bool
    {
        $extra_libs = trim(getenv('SPC_EXTRA_LIBS'));
        $alibs = join('.a ', $this->getLibraries()) . '.a';
        $libs = BUILD_LIB_PATH . '/lib' . str_replace(' ', ' ' . BUILD_LIB_PATH . '/lib', $alibs);
        $extra_libs = str_replace(BUILD_LIB_PATH . '/libgrpc.a', $libs, $extra_libs);
        f_putenv('SPC_EXTRA_LIBS=' . $extra_libs);
        // add -Wno-strict-prototypes
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-grpc=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . '/grpc GRPC_LIB_SUBDIR=' . BUILD_LIB_PATH;
    }

    private function getLibraries(): array
    {
        [, $out] = shell()->execWithResult('$PKG_CONFIG --libs --static grpc');
        $libs = join(' ', $out) . ' -lupb -lupb_message_lib -lupb_json_lib -lupb_textformat_lib -lupb_mini_descriptor_lib -lupb_wire_lib -lupb_mem_lib -lupb_base_lib -lutf8_range';
        $filtered = str_replace('-pthread', '', $libs);
        $filtered = preg_replace('/-L\S+/', '', $filtered);
        $filtered = preg_replace('/(?:\S*\/)?lib([a-zA-Z0-9_+-]+)\.a\b/', '-l$1', $filtered);
        $out = str_replace('-l', '', $filtered);
        $out = preg_replace('/\s+/', ' ', $out);
        return explode(' ', trim($out));
    }
}
