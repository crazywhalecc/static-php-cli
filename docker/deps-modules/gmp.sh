#!/bin/bash
if [ -n "$__MODULE_SH__" ]; then
    return
fi
__MODULE_SH__='gmp.sh'

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
cd ${__DIR__}

# cpu 核数 ，前面为linux 后面为macos
cpu_nums=`nproc 2> /dev/null || sysctl -n hw.ncpu`
# cpu_nums=`grep "processor" /proc/cpuinfo | sort -u | wc -l`

function do_gmp_compiler() {
    pwd
    mkdir -p /app/source/builder_dir/gmp
    tar --strip-components=1 -C ${__DIR__}/source/builder_dir/gmp -xf ${__DIR__}/source/libraries/gmp-6.2.1.tar.lz
    cd ${__DIR__}/source/builder_dir/gmp

    ./configure --prefix=/usr/gmp --enable-static --disable-shared
    make -j $cpu_nums
    echo "gmp compiled!" && \
    make install && \
    echo "gmp compiled!"
    return $?
}

do_gmp_compiler