#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

cd ${__DIR__}

# 新建一个用于放置构建好的二进制的文件夹
mkdir -p dist

{
  num=$(docker ps  | grep 'static-php-dev')
  test $num -gt 0 && docke stop static-php-dev
} || {
  echo $?
}
docker run --rm --name static-php-dev -v $(pwd):/app -it --init  static-php

# 接下来的步骤
#    下载依赖库
#    下载需要扩展
#    编译依赖库
#    准备扩展源码，并移动到构建目录

# 终端会引导你进行编译安装，可选择 PHP 版本、要编译的扩展

