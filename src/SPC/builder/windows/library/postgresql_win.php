<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\exception\FileSystemException;
use SPC\store\FileSystem;

class postgresql_win extends WindowsLibraryBase
{
    public const NAME = 'postgresql-win';

    protected function build(): void
    {
        $builddir = BUILD_ROOT_PATH;
        $envs = '';

        // reset cmake
        FileSystem::resetDir($this->source_dir . '\build');

        copy($this->source_dir . '\pgsql\lib\libpq.lib', BUILD_LIB_PATH . '\libpq.lib');
        copy($this->source_dir . '\pgsql\lib\libpgport.lib', BUILD_LIB_PATH . '\libpgport.lib');
        copy($this->source_dir . '\pgsql\lib\libpgcommon.lib', BUILD_LIB_PATH . '\libpgcommon.lib');

        $headerFiles = ['libpq-fe.h', 'postgres_ext.h'];
        foreach ($headerFiles as $header) {
            copy($this->source_dir . '\pgsql\include\\' . $header, BUILD_INCLUDE_PATH . '\\' . $header);
        }
    }

    private function getVersion(): string
    {
        try {
            $file = FileSystem::readFile($this->source_dir . '/meson.build');
            if (preg_match("/^\\s+version:\\s?'(.*)'/m", $file, $match)) {
                return $match[1];
            }
            return 'unknown';
        } catch (FileSystemException) {
            return 'unknown';
        }
    }
}
