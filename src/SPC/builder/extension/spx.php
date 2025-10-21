<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('spx')]
class spx extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-SPX' . ($shared ? '=shared' : '');
        if ($this->builder->getLib('zlib') !== null) {
            $arg .= ' --with-zlib-dir=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/Makefile.frag',
            '@cp -r assets/web-ui/*',
            '@cp -r ' . $this->source_dir . '/assets/web-ui/*',
        );
        return true;
    }

    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/config.m4',
            'CFLAGS="$CFLAGS -Werror -Wall -O3 -pthread -std=gnu90"',
            'CFLAGS="$CFLAGS -pthread"'
        );
        FileSystem::replaceFileStr(
            $this->source_dir . '/src/php_spx.h',
            "extern zend_module_entry spx_module_entry;\n",
            "extern zend_module_entry spx_module_entry;;\n#define phpext_spx_ptr &spx_module_entry\n"
        );
        FileSystem::copy($this->source_dir . '/src/php_spx.h', $this->source_dir . '/php_spx.h');
        return true;
    }
}
