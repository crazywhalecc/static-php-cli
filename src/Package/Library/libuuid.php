<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SharedLibraryUtil;

#[Library('libuuid')]
class libuuid extends LibraryPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(): void
    {
        UnixCMakeExecutor::create($this)->removeConfigureArgs('-DBUILD_SHARED_LIBS=ON')->toStep(2)->build();
        copy($this->getSourceDir() . '/build/libuuid.a', BUILD_LIB_PATH . '/libuuid.a');
        SharedLibraryUtil::linkArchive($this, 'libuuid.a');
        FileSystem::createDir(BUILD_INCLUDE_PATH . '/uuid');
        copy($this->getSourceDir() . '/uuid.h', BUILD_INCLUDE_PATH . '/uuid/uuid.h');
        $pc = FileSystem::readFile($this->getSourceDir() . '/uuid.pc.in');
        $pc = str_replace([
            '@prefix@',
            '@exec_prefix@',
            '@libdir@',
            '@includedir@',
            '@LIBUUID_VERSION@',
        ], [
            BUILD_ROOT_PATH,
            '${prefix}',
            '${prefix}/lib',
            '${prefix}/include',
            '1.0.3',
        ], $pc);
        FileSystem::writeFile(BUILD_LIB_PATH . '/pkgconfig/uuid.pc', $pc);
    }
}
