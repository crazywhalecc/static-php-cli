<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

class postgresql_win extends WindowsLibraryBase
{
    public const NAME = 'postgresql-win';

    protected function build(): void
    {
        copy($this->source_dir . '\pgsql\lib\libpq.lib', BUILD_LIB_PATH . '\libpq.lib');
        copy($this->source_dir . '\pgsql\lib\libpgport.lib', BUILD_LIB_PATH . '\libpgport.lib');
        copy($this->source_dir . '\pgsql\lib\libpgcommon.lib', BUILD_LIB_PATH . '\libpgcommon.lib');

        // create libpq folder in buildroot/includes/libpq
        if (!file_exists(BUILD_INCLUDE_PATH . '\libpq')) {
            mkdir(BUILD_INCLUDE_PATH . '\libpq');
        }

        $headerFiles = ['libpq-fe.h', 'postgres_ext.h', 'pg_config_ext.h', 'libpq\libpq-fs.h'];
        foreach ($headerFiles as $header) {
            copy($this->source_dir . '\pgsql\include\\' . $header, BUILD_INCLUDE_PATH . '\\' . $header);
        }
    }
}
