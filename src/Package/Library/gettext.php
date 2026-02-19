<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;

#[Library('gettext')]
class gettext
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $pkg, PackageBuilder $builder): void
    {
        $autoconf = UnixAutoconfExecutor::create($pkg)
            ->optionalPackage('ncurses', "--with-libncurses-prefix={$pkg->getBuildRootPath()}")
            ->optionalPackage('libxml2', "--with-libxml2-prefix={$pkg->getBuildRootPath()}")
            ->addConfigureArgs(
                '--disable-java',
                '--disable-c++',
                '--disable-d',
                '--disable-rpath',
                '--disable-modula2',
                '--disable-libasprintf',
                '--with-included-libintl',
                "--with-iconv-prefix={$pkg->getBuildRootPath()}",
            );

        // zts
        if ($builder->getOption('enable-zts')) {
            $autoconf->addConfigureArgs('--enable-threads=isoc+posix')
                ->appendEnv([
                    'CFLAGS' => '-lpthread -D_REENTRANT',
                    'LDFLGAS' => '-lpthread',
                ]);
        } else {
            $autoconf->addConfigureArgs('--disable-threads');
        }

        $autoconf->configure()->make(dir: "{$pkg->getSourceDir()}/gettext-runtime/intl");
        $pkg->patchLaDependencyPrefix();
    }
}
