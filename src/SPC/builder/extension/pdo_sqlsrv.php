<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;

#[CustomExt('pdo_sqlsrv')]
class pdo_sqlsrv extends Extension
{
    public function buildUnixShared(): void
    {
        $config = (new SPCConfigUtil($this->builder))->config([$this->getName()]);
        $env = [
            'CFLAGS' => $config['cflags'],
            'CXXFLAGS' => $config['cflags'],
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        // prepare configure args
        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->execWithEnv(BUILD_BIN_PATH . '/phpize')
            ->execWithEnv('./configure ' . $this->getUnixConfigureArg(true) . ' --with-php-config=' . BUILD_BIN_PATH . '/php-config --enable-shared --disable-static --with-pic')
            ->execWithEnv('make clean')
            ->execWithEnv('make -j' . $this->builder->concurrency)
            ->execWithEnv('make install');
    }
}
