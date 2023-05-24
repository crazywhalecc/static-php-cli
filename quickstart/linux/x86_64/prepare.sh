#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__PROJECT__=$(
  cd ${__DIR__}/../../../
  pwd
)
cd ${__PROJECT__}

composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

chmod +x bin/spc

./bin/spc fetch --all --debug

./bin/spc list-ext


EXTENSION="calendar,ctype,exif,fileinfo,filter,ftp"
EXTENSION="${EXTENSION},session,tokenizer"
EXTENSION="${EXTENSION},phar,posix"
EXTENSION="${EXTENSION},iconv"
EXTENSION="${EXTENSION},xml,dom,simplexml,xmlwriter,xmlreader"
EXTENSION="${EXTENSION},phar,posix"
EXTENSION="${EXTENSION},soap"
EXTENSION="${EXTENSION},mbstring,mbregex"
EXTENSION="${EXTENSION},openssl"
EXTENSION="${EXTENSION},sockets,gmp,bcmath"
EXTENSION="${EXTENSION},pcntl"
EXTENSION="${EXTENSION},curl"
EXTENSION="${EXTENSION},zlib,zip,bz2"
EXTENSION="${EXTENSION},gd"
EXTENSION="${EXTENSION},redis"
EXTENSION="${EXTENSION},pdo,pdo_mysql,pdo_sqlite,"
EXTENSION="${EXTENSION},mysqlnd,sqlite3"
EXTENSION="${EXTENSION},mongodb"


./bin/spc build "${EXTENSION}" --build-cli --cc=clang --cxx=clang++  --debug
# ./bin/spc build "${EXTENSION}" --build-cli --cc=gcc --cxx=g++  --debug
