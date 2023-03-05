#!/bin/env bash

if [ ! "$BASH_VERSION" ]; then
  echo "Please do not use sh to run this script ($0), just execute it directly" 1>&2
  exit 1
fi

cpu_nums=`nproc 2> /dev/null || sysctl -n hw.ncpu`

__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__ROOT__=$(cd ${__DIR__}/../;pwd)
cd ${__DIR__}


if [ -n "$__MODULE_SH__" ]; then
    return
fi
__MODULE_SH__='libmcrypt.sh'

set -exu

function do_libmcrypt_compiler() {

    mkdir -p ${__ROOT__}/source/build_dir/libmcrypt

    tar --strip-components=1 -C ${__ROOT__}/source/build_dir/libmcrypt -xf ${__ROOT__}/source/libraries/libmcrypt-2.5.8-3.4.tar.gz

    cd ${__ROOT__}/source/build_dir/libmcrypt

    chmod a+x ./install-sh
    sh ./configure --prefix=/usr/libmcrypt \
    --enable-static=yes \
    --enable-shared=no

    make -j $cpu_nums
    echo "libmcrypt compiled!"

    make install
    echo "libmcrypt compiled!"

    cd "${__DIR__}"
}
do_libmcrypt_compiler