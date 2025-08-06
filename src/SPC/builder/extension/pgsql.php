<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('pgsql')]
class pgsql extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/configure',
            '/-lpq/',
            $this->getLibFilesString()
        );
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            $libfiles = $this->getLibFilesString();
            $libfiles = str_replace(BUILD_LIB_PATH . '/lib', '-l', $libfiles);
            $libfiles = str_replace('.a', '', $libfiles);
            return '--with-pgsql' . ($shared ? '=shared' : '') .
                ' PGSQL_CFLAGS=-I' . BUILD_INCLUDE_PATH .
                ' PGSQL_LIBS="-L' . BUILD_LIB_PATH . ' ' . $libfiles . '"';
        }
        return '--with-pgsql=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        if ($this->builder->getPHPVersionID() >= 80400) {
            return '--with-pgsql';
        }
        return '--with-pgsql=' . BUILD_ROOT_PATH;
    }

    protected function getExtraEnv(): array
    {
        return [
            'CFLAGS' => '-Wno-int-conversion',
        ];
    }
}
