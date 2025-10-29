<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\PkgConfigUtil;

#[CustomExt('snmp')]
class snmp extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // Overwrite m4 config using newer PHP version
        if ($this->builder->getPHPVersionID() < 80400) {
            FileSystem::copy(ROOT_DIR . '/src/globals/extra/snmp-ext-config-old.m4', "{$this->source_dir}/config.m4");
        }
        $libs = implode(' ', PkgConfigUtil::getLibsArray('netsnmp'));
        FileSystem::replaceFileStr(
            "{$this->source_dir}/config.m4",
            'PHP_EVAL_LIBLINE([$SNMP_LIBS], [SNMP_SHARED_LIBADD])',
            "SNMP_LIBS=\"{$libs}\"\nPHP_EVAL_LIBLINE([\$SNMP_LIBS],  [SNMP_SHARED_LIBADD])"
        );
        return true;
    }
}
