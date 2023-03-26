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

use SPC\exception\RuntimeException;

class nghttp2 extends LinuxLibraryBase
{
    public const NAME = 'nghttp2';

    protected array $static_libs = ['libnghttp2.a'];

    protected array $headers = ['nghttp2'];

    protected array $pkgconfs = [
        'libnghttp2.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: libnghttp2
Description: HTTP/2 C library
URL: https://github.com/tatsuhiro-t/nghttp2
Version: 1.47.0
Libs: -L${libdir} -lnghttp2
Cflags: -I${includedir}
EOF
    ];

    protected array $dep_names = [
        'zlib' => false,
        'openssl' => false,
        'libxml2' => true,
        'libev' => true,
        'libcares' => true,
        'libngtcp2' => true,
        'libnghttp3' => true,
        'libbpf' => true,
        'libevent-openssl' => true,
        'jansson' => true,
        'jemalloc' => true,
        'systemd' => true,
        'cunit' => true,
    ];

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        $args = $this->builder->makeAutoconfArgs(static::NAME, [
            'zlib' => null,
            'openssl' => null,
            'libxml2' => null,
            'libev' => null,
            'libcares' => null,
            'libngtcp2' => null,
            'libnghttp3' => null,
            'libbpf' => null,
            'libevent-openssl' => null,
            'jansson' => null,
            'jemalloc' => null,
            'systemd' => null,
            'cunit' => null,
        ]);

        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->builder->gnu_arch}-unknown-linux " .
                '--enable-lib-only ' .
                '--with-boost=no ' .
                $args . ' ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
    }
}
