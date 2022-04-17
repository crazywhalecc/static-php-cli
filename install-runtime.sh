#!/usr/bin/env bash

function download_file() {
  downloader="wget"
  type wget >/dev/null 2>&1 || { downloader="curl"; }
  if [ "$downloader" = "wget" ]; then
    _down_prefix="O"
  else
    _down_prefix="o"
  fi
  _down_symbol=0
  if [ ! -f "$2" ]; then
    echo $1
    $downloader "$1" -$_down_prefix "$2" >/dev/null 2>&1 && \
    echo "完成！" && _down_symbol=1
  else
    echo "已存在！" && _down_symbol=1
  fi
  if [ $_down_symbol == 0 ]; then
    echo "失败！请检查网络连接！"
    rm -rf "$2"
    return 1
  fi
  return 0
}

function test_composer_and_php() {
  succ=$("$(pwd)/runtime/composer" -n about | grep Manage)
  if [ "$succ" = "" ]; then
    echo "Download PHP binary and composer failed!"
    return 1
  fi
  return 0
}

if [ "$(uname -s)" != "Linux" ]; then
  echo "Only support Linux!!!"
  exit 1
fi

ZM_PHP_VERSION="7.4"
if [ "$ZM_DOWN_PHP_VERSION" != "" ]; then
  ZM_PHP_VERSION="$ZM_DOWN_PHP_VERSION"
  echo "Using custom PHP version: $ZM_PHP_VERSION"
fi

mkdir "$(pwd)/runtime" >/dev/null 2>&1
if [ ! -f "$(pwd)/runtime/php" ]; then
  download_file "https://dl.zhamao.xin/php-bin/down.php?php_ver=$ZM_PHP_VERSION&arch=$(uname -m)" "$(pwd)/runtime/php.tar.gz"
  if [ $? -ne 0 ]; then
    exit 1
  fi
  tar -xf "$(pwd)/runtime/php.tar.gz" -C "$(pwd)/runtime/"
fi
if [ ! -f "$(pwd)/runtime/composer" ]; then
  download_file "https://mirrors.aliyun.com/composer/composer.phar" "$(pwd)/runtime/composer.phar"
  if [ $? -ne 0 ]; then
    exit 1
  fi
  echo '$(dirname $0)/php $(dirname $0)/composer.phar $@' > $(pwd)/runtime/composer
  chmod +x $(pwd)/runtime/composer
  test_composer_and_php
fi
if [ $? -ne 0 ]; then
    exit 1
fi
echo "成功下载！" && \
  echo -e "PHP使用：\truntime/php -v" && \
  echo -e "Composer使用：\truntime/composer"
