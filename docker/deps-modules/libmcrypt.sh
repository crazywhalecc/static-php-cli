#!/bin/bash
if [ -n "$__MODULE_SH__" ]; then
    return
fi
__MODULE_SH__='libmcrypt.sh'

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
cd ${__DIR__}

# cpu 核数 ，前面为linux 后面为macos
cpu_nums=`nproc 2> /dev/null || sysctl -n hw.ncpu`
# cpu_nums=`grep "processor" /proc/cpuinfo | sort -u | wc -l`


function do_libmcrypt_compiler() {
    pwd
    mkdir -p /app/source/builder_dir/libmcrypt
    tar --strip-components=1 -C ${__DIR__}/source/builder_dir/libmcrypt -xf ${__DIR__}/source/libraries/libmcrypt-2.5.8-3.4.tar.gz
    cd ${__DIR__}/source/builder_dir/libmcrypt

    chmod a+x ./install-sh
    sh ./configure --prefix=/usr/libmcrypt \
    --enable-static=yes \
    --enable-shared=no
    make -j $cpu_nums
    echo "libmcrypt compiled!" && \
    make install && \
    echo "libmcrypt compiled!"
    return $?
}
do_libmcrypt_compiler