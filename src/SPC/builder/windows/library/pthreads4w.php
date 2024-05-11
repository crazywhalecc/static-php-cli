<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

class pthreads4w extends WindowsLibraryBase
{
    public const NAME = 'pthreads4w';

    protected function build(): void
    {
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper(
                    'nmake /E /nologo /f Makefile ' .
                    'DESTROOT=' . BUILD_ROOT_PATH . ' ' .
                    'XCFLAGS="/MT" ' . // no dll
                    'EHFLAGS="/I. /DHAVE_CONFIG_H /Os /Ob2 /D__PTW32_STATIC_LIB /D__PTW32_BUILD_INLINED"'
                ),
                'pthreadVC3.inlined_static_stamp'
            );
        copy($this->source_dir . '\libpthreadVC3.lib', BUILD_LIB_PATH . '\libpthreadVC3.lib');
        copy($this->source_dir . '\_ptw32.h', BUILD_INCLUDE_PATH . '\_ptw32.h');
        copy($this->source_dir . '\pthread.h', BUILD_INCLUDE_PATH . '\pthread.h');
        copy($this->source_dir . '\sched.h', BUILD_INCLUDE_PATH . '\sched.h');
        copy($this->source_dir . '\semaphore.h', BUILD_INCLUDE_PATH . '\semaphore.h');
    }
}
