<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

trait xdebug
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec(BUILD_BIN_PATH . '/phpize')
            ->exec('./configure --with-php-config=' . BUILD_BIN_PATH . '/php-config')
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}");
        copy($this->source_dir . '/modules/xdebug.so', BUILD_LIB_PATH . '/xdebug.so');
        copy($this->source_dir . '/modules/xdebug.la', BUILD_LIB_PATH . '/xdebug.la');
    }
}
