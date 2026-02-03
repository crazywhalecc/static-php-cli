<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('curl')]
class curl
{
    #[PatchBeforeBuild]
    #[PatchDescription('Remove CMAKE_C_IMPLICIT_LINK_LIBRARIES and fix macOS framework detection')]
    public function patchBeforeBuild(LibraryPackage $lib): bool
    {
        shell()->cd($lib->getSourceDir())->exec('sed -i.save s@\${CMAKE_C_IMPLICIT_LINK_LIBRARIES}@@ ./CMakeLists.txt');
        if (SystemTarget::getTargetOS() === 'Darwin') {
            FileSystem::replaceFileRegex("{$lib->getSourceDir()}/curl/CMakeLists.txt", '/NOT COREFOUNDATION_FRAMEWORK/m', 'FALSE');
            FileSystem::replaceFileRegex("{$lib->getSourceDir()}/curl/CMakeLists.txt", '/NOT SYSTEMCONFIGURATION_FRAMEWORK/m', 'FALSE');
            FileSystem::replaceFileRegex("{$lib->getSourceDir()}/curl/CMakeLists.txt", '/NOT CORESERVICES_FRAMEWORK/m', 'FALSE');
        }
        return true;
    }

    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->optionalPackage('openssl', '-DCURL_USE_OPENSSL=ON -DCURL_CA_BUNDLE=OFF -DCURL_CA_PATH=OFF -DCURL_CA_FALLBACK=ON', '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF')
            ->optionalPackage('brotli', ...cmake_boolean_args('CURL_BROTLI'))
            ->optionalPackage('libssh2', ...cmake_boolean_args('CURL_USE_LIBSSH2'))
            ->optionalPackage('nghttp2', ...cmake_boolean_args('USE_NGHTTP2'))
            ->optionalPackage('nghttp3', ...cmake_boolean_args('USE_NGHTTP3'))
            ->optionalPackage('ngtcp2', ...cmake_boolean_args('USE_NGTCP2'))
            ->optionalPackage('ldap', ...cmake_boolean_args('CURL_DISABLE_LDAP', true))
            ->optionalPackage('zstd', ...cmake_boolean_args('CURL_ZSTD'))
            ->optionalPackage('idn2', ...cmake_boolean_args('USE_LIBIDN2'))
            ->optionalPackage('psl', ...cmake_boolean_args('CURL_USE_LIBPSL'))
            ->optionalPackage('krb5', ...cmake_boolean_args('CURL_USE_GSSAPI'))
            ->optionalPackage('idn2', ...cmake_boolean_args('CURL_USE_IDN2'))
            ->optionalPackage('libcares', '-DENABLE_ARES=ON')
            ->addConfigureArgs(
                '-DBUILD_CURL_EXE=OFF',
                '-DBUILD_LIBCURL_DOCS=OFF',
            )
            ->build();

        // patch pkgconf
        $lib->patchPkgconfPrefix(['libcurl.pc']);
        shell()->cd("{$lib->getLibDir()}/cmake/CURL/")
            ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"{$lib->getLibDir()}/libcurl.a\"|g' CURLTargets-release.cmake");
    }
}
