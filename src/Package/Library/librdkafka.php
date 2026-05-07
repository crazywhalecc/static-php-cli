<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('librdkafka')]
class librdkafka extends LibraryPackage
{
    #[PatchBeforeBuild]
    #[PatchDescription('Disable rd_ut_coverage_check and define IOV_MAX to avoid build errors')]
    public function patchBeforeBuild(): bool
    {
        FileSystem::replaceFileStr(
            $this->getSourceDir() . '/lds-gen.py',
            "funcs.append('rd_ut_coverage_check')",
            ''
        );
        FileSystem::replaceFileStr(
            $this->getSourceDir() . '/src/rd.h',
            '#error "IOV_MAX not defined"',
            "#define IOV_MAX 1024\n#define __GNU__"
        );
        // Fix OAuthBearer OIDC flag
        FileSystem::replaceFileStr(
            $this->getSourceDir() . '/src/rdkafka_conf.c',
            '#ifdef WITH_OAUTHBEARER_OIDC',
            '#if WITH_OAUTHBEARER_OIDC'
        );
        return true;
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(): void
    {
        UnixCMakeExecutor::create($this)
            ->optionalPackage('zstd', ...cmake_boolean_args('WITH_ZSTD'))
            ->optionalPackage('curl', ...cmake_boolean_args('WITH_CURL'))
            ->optionalPackage('openssl', ...cmake_boolean_args('WITH_SSL'))
            ->optionalPackage('zlib', ...cmake_boolean_args('WITH_ZLIB'))
            ->optionalPackage('liblz4', ...cmake_boolean_args('ENABLE_LZ4_EXT'))
            ->addConfigureArgs(
                '-DWITH_SASL=OFF',
                '-DRDKAFKA_BUILD_STATIC=ON',
                '-DRDKAFKA_BUILD_EXAMPLES=OFF',
                '-DRDKAFKA_BUILD_TESTS=OFF',
            )
            ->build();
    }
}
