<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait gettext
{
    protected function build(): void
    {
        $autoconf = UnixAutoconfExecutor::create($this)
            ->optionalLib('ncurses', "--with-libncurses-prefix={$this->getBuildRootPath()}")
            ->optionalLib('libxml2', "--with-libxml2-prefix={$this->getBuildRootPath()}")
            ->addConfigureArgs(
                '--disable-java',
                '--disable-c++',
                '--disable-d',
                '--disable-rpath',
                '--disable-modula2',
                '--disable-libasprintf',
                '--with-included-libintl',
                "--with-iconv-prefix={$this->getBuildRootPath()}",
            );

        // zts
        if ($this->builder->getOption('enable-zts')) {
            $autoconf->addConfigureArgs('--enable-threads=isoc+posix')
                ->appendEnv([
                    'CFLAGS' => '-lpthread -D_REENTRANT',
                    'LDFLGAS' => '-lpthread',
                ]);
        } else {
            $autoconf->addConfigureArgs('--disable-threads');
        }

        $autoconf->configure()->make(dir: $this->getSourceDir() . '/gettext-runtime/intl');
        $this->patchLaDependencyPrefix();
    }
}
