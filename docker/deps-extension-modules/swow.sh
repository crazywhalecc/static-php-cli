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
__MODULE_SH__='swow.sh'

set -uex


function install_php_extension_swow () {

    mkdir -p ${__ROOT__}/source/build_dir/swow
    cd ${__ROOT__}/source/build_dir/swow
    tar --strip-components=1 -C ${__ROOT__}/source/build_dir/swow -xvf ${__ROOT__}/source/extensions/swow-v1.2.0.tar.gz
    cd ${__ROOT__}/source/build_dir/swow


    # cp -rf ${__ROOT__}/source/

}


install_php_extension_swow