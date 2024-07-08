<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\windows\SystemUtil;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

class openssl extends WindowsLibraryBase
{
    public const NAME = 'openssl';

    protected function build(): void
    {
        $perl_path_native = PKG_ROOT_PATH . '\strawberry-perl-' . arch2gnu(php_uname('m')) . '-win\perl\bin\perl.exe';
        $perl = file_exists($perl_path_native) ? ($perl_path_native) : SystemUtil::findCommand('perl.exe');
        if ($perl === null) {
            throw new RuntimeException('You need to install perl first! (easiest way is using static-php-cli command "doctor")');
        }
        cmd()->cd($this->source_dir)
            ->execWithWrapper(
                $this->builder->makeSimpleWrapper($perl),
                'Configure zlib VC-WIN64A ' .
                'no-shared ' .
                '--prefix=' . quote(BUILD_ROOT_PATH) . ' ' .
                '--with-zlib-lib=' . quote(BUILD_LIB_PATH) . ' ' .
                '--with-zlib-include=' . quote(BUILD_INCLUDE_PATH) . ' ' .
                '--release ' .
                'no-legacy '
            );

        // patch zlib
        FileSystem::replaceFileStr($this->source_dir . '\Makefile', 'ZLIB1', 'zlibstatic.lib');
        // patch debug: https://stackoverflow.com/questions/18486243/how-do-i-build-openssl-statically-linked-against-windows-runtime
        FileSystem::replaceFileStr($this->source_dir . '\Makefile', '/debug', '/incremental:no /opt:icf /dynamicbase /nxcompat /ltcg /nodefaultlib:msvcrt');
        cmd()->cd($this->source_dir)->execWithWrapper(
            $this->builder->makeSimpleWrapper('nmake'),
            'install_dev ' .
            'CNF_LDFLAGS="/NODEFAULTLIB:kernel32.lib /NODEFAULTLIB:msvcrt /NODEFAULTLIB:msvcrtd /DEFAULTLIB:libcmt /LIBPATH:' . BUILD_LIB_PATH . ' zlibstatic.lib"'
        );
        copy($this->source_dir . '\ms\applink.c', BUILD_INCLUDE_PATH . '\openssl\applink.c');

        FileSystem::replaceFileRegex(
            BUILD_LIB_PATH . '\cmake\OpenSSL\OpenSSLConfig.cmake',
            '/set\(OPENSSL_LIBCRYPTO_DEPENDENCIES .*\)/m',
            'set(OPENSSL_LIBCRYPTO_DEPENDENCIES "${OPENSSL_LIBRARY_DIR}" ws2_32.lib gdi32.lib advapi32.lib crypt32.lib user32.lib)'
        );
    }
}
