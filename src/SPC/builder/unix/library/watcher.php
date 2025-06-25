<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait watcher
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        shell()->cd($this->source_dir . '/watcher-c')
            ->initializeEnv($this)
            ->exec(getenv('CC') . ' -c -o libwatcher-c.o ./src/watcher-c.cpp -I ./include -I ../include -std=c++17 -Wall -Wextra -fPIC')
            ->exec(getenv('AR') . ' rcs libwatcher-c.a libwatcher-c.o');

        copy($this->source_dir . '/watcher-c/libwatcher-c.a', BUILD_LIB_PATH . '/libwatcher-c.a');
        FileSystem::createDir(BUILD_INCLUDE_PATH . '/wtr');
        copy($this->source_dir . '/watcher-c/include/wtr/watcher-c.h', BUILD_INCLUDE_PATH . '/wtr/watcher-c.h');
    }
}
