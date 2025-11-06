<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait librdkafka
{
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr(
            $this->source_dir . '/lds-gen.py',
            "funcs.append('rd_ut_coverage_check')",
            ''
        );
        FileSystem::replaceFileStr(
            $this->source_dir . '/src/rd.h',
            '#error "IOV_MAX not defined"',
            "#define IOV_MAX 1024\n#define __GNU__"
        );
        return true;
    }

    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalLib('zstd', ...cmake_boolean_args('WITH_ZSTD'))
            ->optionalLib('curl', ...cmake_boolean_args('WITH_CURL'))
            ->optionalLib('openssl', ...cmake_boolean_args('WITH_SSL'))
            ->optionalLib('zlib', ...cmake_boolean_args('WITH_ZLIB'))
            ->optionalLib('liblz4', ...cmake_boolean_args('ENABLE_LZ4_EXT'))
            ->addConfigureArgs(
                '-DWITH_SASL=OFF',
                '-DRDKAFKA_BUILD_STATIC=ON',
                '-DRDKAFKA_BUILD_EXAMPLES=OFF',
                '-DRDKAFKA_BUILD_TESTS=OFF',
            )
            ->build();
    }
}
