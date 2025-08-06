<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait libuuid
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->toStep(2)->build();
        copy($this->source_dir . '/build/libuuid.a', BUILD_LIB_PATH . '/libuuid.a');
        FileSystem::createDir(BUILD_INCLUDE_PATH . '/uuid');
        copy($this->source_dir . '/uuid.h', BUILD_INCLUDE_PATH . '/uuid/uuid.h');
        $pc = FileSystem::readFile($this->source_dir . '/uuid.pc.in');
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
