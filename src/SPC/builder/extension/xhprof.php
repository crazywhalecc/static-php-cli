<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;

#[CustomExt('xhprof')]
class xhprof extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if (!is_link(SOURCE_PATH . '/php-src/ext/xhprof')) {
            if (PHP_OS_FAMILY === 'Windows') {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && mklink /D xhprof xhprof-src\extension');
            } else {
                f_passthru('cd ' . SOURCE_PATH . '/php-src/ext && ln -s xhprof-src/extension xhprof');
            }

            // patch config.m4
            FileSystem::replaceFileStr(
                SOURCE_PATH . '/php-src/ext/xhprof/config.m4',
                'if test -f $phpincludedir/ext/pcre/php_pcre.h; then',
                'if test -f $abs_srcdir/ext/pcre/php_pcre.h; then'
            );
            return true;
        }
        return false;
    }

    public function buildUnixShared(): void
    {
        $config = (new SPCConfigUtil($this->builder))->config([$this->getName()]);
        $env = [
            'CFLAGS' => $config['cflags'],
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
            'LD_LIBRARY_PATH' => BUILD_LIB_PATH,
        ];
        // prepare configure args
        shell()->cd($this->source_dir . '/extension')
            ->setEnv($env)
            ->execWithEnv(BUILD_BIN_PATH . '/phpize');

        if ($this->patchBeforeSharedConfigure()) {
            logger()->info('ext [ . ' . $this->getName() . '] patching before shared configure');
        }

        shell()->cd($this->source_dir . '/extension')
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
