<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SPCConfigUtil;

#[Library('libxslt')]
class libxslt
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, PackageInstaller $installer): void
    {
        $static_libs = new SPCConfigUtil(['libs_only_deps' => true, 'no_php'])->getPackageDepsConfig($lib->getName(), array_keys($installer->getResolvedPackages()));
        $cpp = SystemTarget::getTargetOS() === 'Darwin' ? '-lc++' : '-lstdc++';
        $ac = UnixAutoconfExecutor::create($lib)
            ->appendEnv([
                'CFLAGS' => "-I{$lib->getIncludeDir()}",
                'LDFLAGS' => "-L{$lib->getLibDir()}",
                'LIBS' => "{$static_libs['libs']} {$cpp}",
            ])
            ->addConfigureArgs(
                '--without-python',
                '--without-crypto',
                '--without-debug',
                '--without-debugger',
                "--with-libxml-prefix={$lib->getBuildRootPath()}",
            );
        if (getenv('SPC_LD_LIBRARY_PATH') && getenv('SPC_LIBRARY_PATH')) {
            $ac->appendEnv([
                'LD_LIBRARY_PATH' => getenv('SPC_LD_LIBRARY_PATH'),
                'LIBRARY_PATH' => getenv('SPC_LIBRARY_PATH'),
            ]);
        }
        $ac->configure()->make();

        $lib->patchPkgconfPrefix(['libexslt.pc', 'libxslt.pc']);
        $lib->patchLaDependencyPrefix();
        $AR = getenv('AR') ?: 'ar';
        shell()->cd($lib->getLibDir())
            ->exec("{$AR} -t libxslt.a | grep '\\.a$' | xargs -n1 {$AR} d libxslt.a")
            ->exec("{$AR} -t libexslt.a | grep '\\.a$' | xargs -n1 {$AR} d libexslt.a");
    }
}
