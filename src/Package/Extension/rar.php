<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('rar')]
class rar extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('rar extension workaround for newer Xcode clang (>= 15.0)')]
    public function patchBeforeBuildconf(): void
    {
        // workaround for newer Xcode clang (>= 15.0)
        if (SystemTarget::getTargetOS() === 'Darwin') {
            FileSystem::replaceFileStr("{$this->getBuildDir()}/config.m4", '-Wall -fvisibility=hidden', '-Wall -Wno-incompatible-function-pointer-types -fvisibility=hidden');
        }
    }

    /**
     * php-rar 4.3.1+ GitHub release tarballs omit rar.map, but config.m4 probes
     * and links every build with -Wl,--version-script=$ext_srcdir/rar.map. The
     * configure probe links an executable, where ld.lld tolerates the missing
     * file, so configure reports "yes" and the real shared-library link fails
     * with "cannot find version script". Recreate the file (content matches
     * upstream master) when missing, before configure on both the shared
     * phpize path and the static in-tree path.
     */
    #[BeforeStage('ext-rar', [self::class, 'configureForUnix'])]
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('Recreate rar.map omitted from php-rar release tarballs but required by config.m4')]
    public function patchMissingRarMap(): void
    {
        $mapFile = "{$this->getBuildDir()}/rar.map";
        if (!file_exists($mapFile)) {
            FileSystem::writeFile($mapFile, "{ global: get_module; local: *; };\n");
        }
    }
}
