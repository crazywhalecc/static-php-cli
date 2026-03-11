<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('memcache')]
class memcache extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-memcache')]
    public function patchBeforeBuildconf(): bool
    {
        if (!$this->isBuildStatic()) {
            return false;
        }
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config9.m4",
            'if test -d $abs_srcdir/src ; then',
            'if test -d $abs_srcdir/main ; then'
        );
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config9.m4",
            'export CPPFLAGS="$CPPFLAGS $INCLUDES"',
            'export CPPFLAGS="$CPPFLAGS $INCLUDES -I$abs_srcdir/main"'
        );
        // add for in-tree building
        file_put_contents(
            "{$this->getSourceDir()}/php_memcache.h",
            <<<'EOF'
#ifndef PHP_MEMCACHE_H
#define PHP_MEMCACHE_H

extern zend_module_entry memcache_module_entry;
#define phpext_memcache_ptr &memcache_module_entry

#endif
EOF
        );
        return true;
    }

    #[BeforeStage('ext-memcache', [self::class, 'configureForUnix'])]
    #[PatchDescription('Fix memcache extension compile error when building as shared')]
    public function patchBeforeSharedConfigure(): bool
    {
        if (!$this->isBuildShared()) {
            return false;
        }
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config9.m4",
            'if test -d $abs_srcdir/main ; then',
            'if test -d $abs_srcdir/src ; then',
        );
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config9.m4",
            'export CPPFLAGS="$CPPFLAGS $INCLUDES -I$abs_srcdir/main"',
            'export CPPFLAGS="$CPPFLAGS $INCLUDES"',
        );
        return true;
    }

    public function getSharedExtensionEnv(): array
    {
        $parent = parent::getSharedExtensionEnv();
        $parent['CFLAGS'] .= ' -std=c17';
        return $parent;
    }
}
