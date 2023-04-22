#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

test -f /etc/apk/repositories.save || cp /etc/apk/repositories /etc/apk/repositories.save
sed -i 's/dl-cdn.alpinelinux.org/mirrors.tuna.tsinghua.edu.cn/g' /etc/apk/repositories

apk update

apk add vim alpine-sdk xz autoconf automake linux-headers clang-dev clang lld libtool cmake bison re2c gettext coreutils