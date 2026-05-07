<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\PkgConfigUtil;

#[Extension('snmp')]
class snmp extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-snmp')]
    #[PatchDescription('Fix snmp extension compile error when building with older PHP version and newer net-snmp library')]
    public function patchBeforeBuildconf(): bool
    {
        // Overwrite m4 config using newer PHP version
        if (php::getPHPVersionID() < 80400) {
            FileSystem::copy(ROOT_DIR . '/src/globals/extra/snmp-ext-config-old.m4', "{$this->getSourceDir()}/config.m4");
        }
        $libs = implode(' ', PkgConfigUtil::getLibsArray('netsnmp'));
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config.m4",
            'PHP_EVAL_LIBLINE([$SNMP_LIBS], [SNMP_SHARED_LIBADD])',
            "SNMP_LIBS=\"{$libs}\"\nPHP_EVAL_LIBLINE([\$SNMP_LIBS],  [SNMP_SHARED_LIBADD])"
        );
        return true;
    }
}
