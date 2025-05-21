<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;

#[CustomExt('sqlsrv')]
class sqlsrv extends Extension
{
    private bool $pdo_sqlsrv_patched = false;

    public function patchBeforeBuildconf(): bool
    {
        if (PHP_OS_FAMILY === 'Windows' && $this->builder->getExt('pdo_sqlsrv') === null) {
            // support sqlsrv without pdo_sqlsrv
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32', 'PHP_PDO_SQLSRV', '"no"');
            $this->pdo_sqlsrv_patched = true;
            return true;
        }
        return false;
    }

    public function patchBeforeConfigure(): bool
    {
        if ($this->pdo_sqlsrv_patched) {
            // revert pdo_sqlsrv patch
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/sqlsrv/config.w32', '"no" == "no"', 'PHP_PDO_SQLSRV == "no"');
            return true;
        }
        return false;
    }

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
            ->execWithEnv(BUILD_BIN_PATH . '/phpize');

        if ($this->patchBeforeSharedConfigure()) {
            logger()->info('ext [ . ' . $this->getName() . '] patching before shared configure');
        }

        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->execWithEnv('./configure ' . $this->getUnixConfigureArg(true) . ' --with-php-config=' . BUILD_BIN_PATH . '/php-config --with-pic')
            ->execWithEnv('make clean')
            ->execWithEnv('make -j' . $this->builder->concurrency)
            ->execWithEnv('make install');

        // check shared extension with php-cli
        if (file_exists(BUILD_BIN_PATH . '/php')) {
            $this->runSharedExtensionCheckUnix();
        }
    }
}
