<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;

#[CustomExt('dom')]
class dom extends Extension
{
    /**
     * @throws RuntimeException
     */
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-dom';
        if (!$shared) {
            $arg .= ' --with-libxml="' . BUILD_ROOT_PATH . '"';
        }
        return $arg;
    }

    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/win32/build/config.w32', 'dllmain.c ', '');
        return true;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--with-dom --with-libxml';
    }

    public function buildUnixShared(): void
    {
        $config = (new SPCConfigUtil($this->builder))->config([$this->getName()]);
        $env = [
            'CFLAGS' => $config['cflags'] . ' -I' . SOURCE_PATH . '/php-src',
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        // prepare configure args
        shell()->cd($this->source_dir)
            ->setEnv($env)
            ->execWithEnv(BUILD_BIN_PATH . '/phpize')
            ->execWithEnv('./configure ' . $this->getUnixConfigureArg(true) . ' --with-php-config=' . BUILD_BIN_PATH . '/php-config --enable-shared --disable-static')
            ->execWithEnv('make clean')
            ->execWithEnv('make -j' . $this->builder->concurrency);

        // copy shared library
        FileSystem::createDir(BUILD_MODULES_PATH);
        $extensionDirFile = (getenv('EXTENSION_DIR') ?: $this->source_dir . '/modules') . '/' . $this->getName() . '.so';
        $sourceDirFile = $this->source_dir . '/modules/' . $this->getName() . '.so';
        if (file_exists($extensionDirFile)) {
            copy($extensionDirFile, BUILD_MODULES_PATH . '/' . $this->getName() . '.so');
        } elseif (file_exists($sourceDirFile)) {
            copy($sourceDirFile, BUILD_MODULES_PATH . '/' . $this->getName() . '.so');
        } else {
            throw new RuntimeException('extension ' . $this->getName() . ' built successfully, but into an unexpected location.');
        }
        // check shared extension with php-cli
        if (file_exists(BUILD_BIN_PATH . '/php')) {
            $this->runSharedExtensionCheckUnix();
        }
    }
}
