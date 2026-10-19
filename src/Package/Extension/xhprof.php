<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('xhprof')]
class xhprof extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-xhprof')]
    public function patchBeforeBuildconf(): bool
    {
        // patch config.m4
        FileSystem::replaceFileStr(
            "{$this->getBuildDir()}/config.m4",
            'if test -f $phpincludedir/ext/pcre/php_pcre.h; then',
            'if test -f $abs_srcdir/ext/pcre/php_pcre.h; then'
        );
        return true;
    }
}
