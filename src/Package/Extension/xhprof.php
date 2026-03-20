<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('xhprof')]
class xhprof extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-xhprof')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        $link = "{$php_src}/ext/xhprof";
        if (!is_link($link)) {
            shell()->cd("{$php_src}/ext")->exec('ln -s xhprof-src/extension xhprof');

            // patch config.m4
            FileSystem::replaceFileStr(
                "{$this->getSourceDir()}/extension/config.m4",
                'if test -f $phpincludedir/ext/pcre/php_pcre.h; then',
                'if test -f $abs_srcdir/ext/pcre/php_pcre.h; then'
            );
            return true;
        }
        return false;
    }
}
