<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;

trait grpc
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)
            ->exec('EXTRA_DEFINES=GRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK EMBED_OPENSSL=false CXXFLAGS="-L' . BUILD_LIB_PATH . ' -I' . BUILD_INCLUDE_PATH . '" make static -j' . $this->builder->concurrency);
        copy($this->source_dir . '/libs/opt/libgrpc.a', BUILD_LIB_PATH . '/libgrpc.a');
        copy($this->source_dir . '/libs/opt/libboringssl.a', BUILD_LIB_PATH . '/libboringssl.a');
        if (!file_exists(BUILD_LIB_PATH . '/libcares.a')) {
            copy($this->source_dir . '/libs/opt/libcares.a', BUILD_LIB_PATH . '/libcares.a');
        }
        FileSystem::copyDir($this->source_dir . '/include/grpc', BUILD_INCLUDE_PATH . '/grpc');
        FileSystem::copyDir($this->source_dir . '/include/grpc++', BUILD_INCLUDE_PATH . '/grpc++');
        FileSystem::copyDir($this->source_dir . '/include/grpcpp', BUILD_INCLUDE_PATH . '/grpcpp');
        FileSystem::copyDir($this->source_dir . '/src/php/ext/grpc', BUILD_ROOT_PATH . '/grpc_php_ext_src');
    }
}
