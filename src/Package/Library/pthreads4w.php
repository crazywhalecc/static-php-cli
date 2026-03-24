<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Util\FileSystem;

#[Library('pthreads4w')]
class pthreads4w
{
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        cmd()->cd($lib->getSourceDir())
            ->exec(
                'nmake /E /nologo /f Makefile ' .
                'DESTROOT=' . $lib->getBuildRootPath() . ' ' .
                'XCFLAGS="/MT" ' . // no dll
                'EHFLAGS="/I. /DHAVE_CONFIG_H /Os /Ob2 /D__PTW32_STATIC_LIB /D__PTW32_BUILD_INLINED" ' .
                'pthreadVC3.inlined_static_stamp'
            );
        FileSystem::createDir($lib->getLibDir());
        FileSystem::createDir($lib->getIncludeDir());
        FileSystem::copy("{$lib->getSourceDir()}\\libpthreadVC3.lib", "{$lib->getLibDir()}\\libpthreadVC3.lib");
        FileSystem::copy("{$lib->getSourceDir()}\\_ptw32.h", "{$lib->getIncludeDir()}\\_ptw32.h");
        FileSystem::copy("{$lib->getSourceDir()}\\pthread.h", "{$lib->getIncludeDir()}\\pthread.h");
        FileSystem::copy("{$lib->getSourceDir()}\\sched.h", "{$lib->getIncludeDir()}\\sched.h");
        FileSystem::copy("{$lib->getSourceDir()}\\semaphore.h", "{$lib->getIncludeDir()}\\semaphore.h");
    }
}
