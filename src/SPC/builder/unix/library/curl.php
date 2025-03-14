<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait curl
{
    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        $extra = '';
        // lib:openssl
        $extra .= $this->builder->getLib('openssl') ? '-DCURL_USE_OPENSSL=ON ' : '-DCURL_USE_OPENSSL=OFF -DCURL_ENABLE_SSL=OFF ';
        // lib:brotli
        $extra .= $this->builder->getLib('brotli') ? '-DCURL_BROTLI=ON ' : '-DCURL_BROTLI=OFF ';
        // lib:libssh2
        $libssh2 = $this->builder->getLib('libssh2');
        if ($this->builder->getLib('libssh2')) {
            /* @phpstan-ignore-next-line */
            $extra .= '-DLIBSSH2_LIBRARY="' . $libssh2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DLIBSSH2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DCURL_USE_LIBSSH2=OFF ';
        }
        // lib:nghttp2
        if ($nghttp2 = $this->builder->getLib('nghttp2')) {
            $extra .= '-DUSE_NGHTTP2=ON ' .
                /* @phpstan-ignore-next-line */
                '-DNGHTTP2_LIBRARY="' . $nghttp2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DNGHTTP2_INCLUDE_DIR="' . BUILD_INCLUDE_PATH . '" ';
        } else {
            $extra .= '-DUSE_NGHTTP2=OFF ';
        }
        // lib:ldap
        $extra .= $this->builder->getLib('ldap') ? '-DCURL_DISABLE_LDAP=OFF ' : '-DCURL_DISABLE_LDAP=ON ';
        // lib:zstd
        $extra .= $this->builder->getLib('zstd') ? '-DCURL_ZSTD=ON ' : '-DCURL_ZSTD=OFF ';
        // lib:idn2
        $extra .= $this->builder->getLib('idn2') ? '-DUSE_LIBIDN2=ON ' : '-DUSE_LIBIDN2=OFF ';
        // lib:psl
        $extra .= $this->builder->getLib('psl') ? '-DCURL_USE_LIBPSL=ON ' : '-DCURL_USE_LIBPSL=OFF ';
        // lib:libcares
        $extra .= $this->builder->getLib('libcares') ? '-DENABLE_ARES=ON ' : '';

        FileSystem::resetDir($this->source_dir . '/build');

        // compile！
        shell()->cd($this->source_dir . '/build')
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags(),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->exec('sed -i.save s@\${CMAKE_C_IMPLICIT_LINK_LIBRARIES}@@ ../CMakeLists.txt')
            ->execWithEnv("cmake {$this->builder->makeCmakeArgs()} -DBUILD_SHARED_LIBS=OFF -DBUILD_CURL_EXE=OFF -DBUILD_LIBCURL_DOCS=OFF {$extra} ..")
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv('make install');
        // patch pkgconf
        $this->patchPkgconfPrefix(['libcurl.pc']);
        shell()->cd(BUILD_LIB_PATH . '/cmake/CURL/')
            ->exec("sed -ie 's|\"/lib/libcurl.a\"|\"" . BUILD_LIB_PATH . "/libcurl.a\"|g' CURLTargets-release.cmake");
    }
}
