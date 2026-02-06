<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\GccNativeToolchain;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\LinuxUtil;

#[Library('liburing')]
class liburing extends LibraryPackage
{
    #[PatchBeforeBuild]
    #[PatchDescription('Fix realpath usage for musl-based distributions')]
    public function patchBeforeBuild(): bool
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Linux', 'This patch is only for Linux systems.');
        if (LinuxUtil::isMuslDist()) {
            FileSystem::replaceFileStr("{$this->getSourceDir()}/configure", 'realpath -s', 'realpath');
            return true;
        }
        return false;
    }

    #[BuildFor('Linux')]
    public function buildLinux(ToolchainInterface $toolchain): void
    {
        $use_libc = !$toolchain instanceof GccNativeToolchain || version_compare(SystemTarget::getLibcVersion(), '2.30', '>=');
        $make = UnixAutoconfExecutor::create($this);

        if ($use_libc) {
            $make->appendEnv([
                'CFLAGS' => '-D_GNU_SOURCE',
            ]);
        }

        $make
            ->removeConfigureArgs(
                '--disable-shared',
                '--enable-static',
                '--with-pic',
                '--enable-pic',
            )
            ->addConfigureArgs(
                $use_libc ? '--use-libc' : '',
            )
            ->configure()
            ->make('library ENABLE_SHARED=0', 'install ENABLE_SHARED=0', with_clean: false);

        $this->patchPkgconfPrefix();
    }
}
