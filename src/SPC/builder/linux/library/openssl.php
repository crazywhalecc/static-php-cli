<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\linux\SystemUtil;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;

class openssl extends LinuxLibraryBase
{
    public const NAME = 'openssl';

    protected array $static_libs = [
        'libssl.a',
        'libcrypto.a',
    ];

    protected array $headers = ['openssl'];

    protected array $pkgconfs = [
        'openssl.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: OpenSSL
Description: Secure Sockets Layer and cryptography libraries and tools
Version: 3.0.3
Requires: libssl libcrypto
EOF,
        'libssl.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: OpenSSL-libssl
Description: Secure Sockets Layer and cryptography libraries
Version: 3.0.3
Requires.private: libcrypto
Libs: -L${libdir} -lssl
Cflags: -I${includedir}
EOF,
        'libcrypto.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include
enginesdir=${libdir}/engines-3

Name: OpenSSL-libcrypto
Description: OpenSSL cryptography library
Version: 3.0.3
Libs: -L${libdir} -lcrypto
Libs.private: -lz -ldl -pthread 
Cflags: -I${includedir}
EOF,
    ];

    protected array $dep_names = ['zlib' => true];

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function build()
    {
        [$lib,$include,$destdir] = SEPARATED_PATH;

        $extra = '';
        $ex_lib = '-ldl -pthread';

        $env = $this->builder->pkgconf_env . " CFLAGS='{$this->builder->arch_c_flags}'";
        $env .= " CC='{$this->builder->cc} --static -static-libgcc -idirafter " . BUILD_INCLUDE_PATH .
            ' -idirafter /usr/include/ ' .
            ' -idirafter /usr/include/' . $this->builder->arch . '-linux-gnu/ ' .
            "' ";
        // lib:zlib
        $zlib = $this->builder->getLib('zlib');
        if ($zlib instanceof LinuxLibraryBase) {
            $extra = 'zlib';
            $ex_lib = trim($zlib->getStaticLibFiles() . ' ' . $ex_lib);
            $zlib_extra =
                '--with-zlib-include=' . BUILD_INCLUDE_PATH . ' ' .
                '--with-zlib-lib=' . BUILD_LIB_PATH . ' ';
        } else {
            $zlib_extra = '';
        }

        $ex_lib = trim($ex_lib);

        $clang_postfix = SystemUtil::getCCType($this->builder->cc) === 'clang' ? '-clang' : '';

        f_passthru(
            $this->builder->set_x . ' && ' .
            "cd {$this->source_dir} && " .
            "{$this->builder->configure_env} {$env} ./Configure no-shared {$extra} " .
            '--prefix=/ ' . // use prefix=/
            "--libdir={$lib} " .
            '--static -static ' .
            "{$zlib_extra}" .
            'no-legacy ' .
            "linux-{$this->builder->arch}{$clang_postfix} && " .
            'make clean && ' .
            "make -j{$this->builder->concurrency} CNF_EX_LIBS=\"{$ex_lib}\" && " .
            'make install_sw DESTDIR=' . $destdir
            // remove liblegacy
            // 'ar t lib/libcrypto.a | grep -e \'^liblegacy-\' | xargs ar d lib/libcrypto.a'
        );
    }
}
