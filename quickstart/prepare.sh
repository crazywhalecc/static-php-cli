#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__PROJECT__=$(
  cd ${__DIR__}/../
  pwd
)
cd ${__PROJECT__}

OS=$(uname -s)
ARCH=$(uname -m)

if [[ $OS = "Linux" && -f /etc/os-release ]]; then
  OS_NAME=$(cat /etc/os-release | grep '^ID=' | awk -F '=' '{print $2}')
  # debian ubuntu alpine
fi

composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

chmod +x bin/spc

./bin/spc fetch --all --debug

./bin/spc list-ext

EXTENSIONS="calendar,ctype,exif,fileinfo,filter,ftp"
EXTENSIONS="${EXTENSIONS},session,tokenizer"
EXTENSIONS="${EXTENSIONS},phar,posix"
EXTENSIONS="${EXTENSIONS},iconv"
EXTENSIONS="${EXTENSIONS},xml,dom,simplexml,xmlwriter,xmlreader"
EXTENSIONS="${EXTENSIONS},phar,posix"
EXTENSIONS="${EXTENSIONS},soap"
EXTENSIONS="${EXTENSIONS},mbstring,mbregex"
EXTENSIONS="${EXTENSIONS},openssl"
EXTENSIONS="${EXTENSIONS},sockets,gmp,bcmath"
EXTENSIONS="${EXTENSIONS},pcntl"
EXTENSIONS="${EXTENSIONS},curl"
EXTENSIONS="${EXTENSIONS},zlib,zip,bz2"
EXTENSIONS="${EXTENSIONS},gd"
EXTENSIONS="${EXTENSIONS},redis"
EXTENSIONS="${EXTENSIONS},pdo,pdo_mysql,pdo_sqlite"
EXTENSIONS="${EXTENSIONS},mysqlnd,sqlite3"
EXTENSIONS="${EXTENSIONS},mongodb"
# EXTENSIONS="${EXTENSIONS},swoole"
EXTENSIONS="${EXTENSIONS},swow"

./bin/spc build "${EXTENSIONS}" --build-cli --cc=clang --cxx=clang++ --debug
# ./bin/spc build "${EXTENSIONS}" --build-cli --cc=gcc --cxx=g++  --debug
