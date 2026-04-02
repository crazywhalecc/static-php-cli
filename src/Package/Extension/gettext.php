<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('gettext')]
class gettext
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-gettext')]
    #[PatchDescription('Patch gettext extension config.m4 to fix library detection on macOS')]
    public function patchBeforeBuildconf(PackageInstaller $installer): void
    {
        spc_skip_unless(SystemTarget::getTargetOS() === 'Darwin', 'gettext extension patch is only needed on macOS');
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        FileSystem::replaceFileStr(
            "{$php_src}/ext/gettext/config.m4",
            ['AC_CHECK_LIB($GETTEXT_CHECK_IN_LIB', 'AC_CHECK_LIB([$GETTEXT_CHECK_IN_LIB'],
            ['AC_CHECK_LIB(intl', 'AC_CHECK_LIB([intl'] // new php versions use a bracket
        );
    }
}
