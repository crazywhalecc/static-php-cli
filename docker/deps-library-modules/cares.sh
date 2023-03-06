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
__MODULE_SH__='cares.sh'

set -uex


function do_cares_compiler() {

    mkdir -p ${__ROOT__}/source/build_dir/cares

    tar --strip-components=1 -C ${__ROOT__}/source/build_dir/cares -xvf ${__ROOT__}/source/libraries/c-ares-1.19.0.tar.gz

    cd ${__ROOT__}/source/build_dir/cares

    ./configure --prefix=/usr/cares --enable-static --disable-shared

    make -j "$cpu_nums"
    echo "cares compiled!"

    make install
    echo "cares compiled!"

    cd ${__DIR__}
}

do_cares_compiler