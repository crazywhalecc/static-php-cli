<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\LinuxUtil;

#[Extension('ffi')]
class ffi extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-ffi')]
    #[PatchDescription('Patch FFI extension on CentOS 7 with -O3 optimization (strncmp issue)')]
    public function patchBeforeBuildconf(): void
    {
        spc_skip_if(!($ver = SystemTarget::getLibcVersion()) || version_compare($ver, '2.17', '>'));
        $ver_id = php::getPHPVersionID(return_null_if_failed: true);
        spc_skip_if($ver_id === null || $ver_id < 80316);
        spc_skip_if(LinuxUtil::getOSRelease()['dist'] !== 'centos');
        SourcePatcher::patchFile('ffi_centos7_fix_O3_strncmp.patch', SOURCE_PATH . '/php-src');
    }
}
