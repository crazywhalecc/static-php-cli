<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('ldap')]
class ldap extends Extension
{
    public function patchBeforeConfigure(): bool
    {
        $output = shell()->execWithResult('$PKG_CONFIG --libs-only-l --static ldap');
        if (!empty($output[1][0])) {
            $libs = $output[1][0];
            FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lldap ', $libs . ' ');
        }
        return true;
    }
}
