<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\linux\SystemUtil;
use SPC\store\FileSystem;
use SPC\toolchain\GccNativeToolchain;
use SPC\toolchain\ToolchainManager;
use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

class liburing extends LinuxLibraryBase
{
    public const NAME = 'liburing';

    public function patchBeforeBuild(): bool
    {
        if (SystemUtil::isMuslDist()) {
            FileSystem::replaceFileStr($this->source_dir . '/configure', 'realpath -s', 'realpath');
            return true;
        }
        return false;
    }

    protected function build(): void
    {
        $use_libc = ToolchainManager::getToolchainClass() !== GccNativeToolchain::class || version_compare(SPCTarget::getLibcVersion(), '2.30', '>=');
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
