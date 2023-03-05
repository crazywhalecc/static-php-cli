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
__MODULE_SH__='gmp.sh'

set -uex


function do_gmp_compiler() {

    mkdir -p ${__ROOT__}/source/build_dir/gmp

    tar --strip-components=1 -C ${__ROOT__}/source/build_dir/gmp -xvf ${__ROOT__}/source/libraries/gmp-6.2.1.tar.lz

    cd ${__ROOT__}/source/build_dir/gmp

    ./configure --prefix=/usr/gmp --enable-static --disable-shared

    make -j "$cpu_nums"
    echo "gmp compiled!"

    make install
    echo "gmp compiled!"

    cd ${__DIR__}
}

do_gmp_compiler