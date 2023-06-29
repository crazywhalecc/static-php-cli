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
  OS_ID=$(cat /etc/os-release | grep '^ID=' | awk -F '=' '{print $2}')
  case $OS_ID in
  debian | alpine | ubuntu)
    echo $OS_ID
    ;;
  *)
    echo 'NO SUPPORT LINUX OS'
    exit 0
    ;;
  esac
fi

# sh bin/setup-runtime --mirror china

export PATH="${__PROJECT__}/bin:$PATH"

alias php="php -d curl.cainfo=${__PROJECT__}/bin/cacert.pem -d openssl.cafile=${__PROJECT__}/bin/cacert.pem"

# php --ri curl
# php --ri openssl

export COMPOSER_ALLOW_SUPERUSER=1
#composer suggests --all
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
#composer update --no-dev --optimize-autoloader
#composer update  --optimize-autoloader

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
EXTENSIONS="${EXTENSIONS},pdo"
# EXTENSIONS="pdo"
EXTENSIONS="${EXTENSIONS},pgsql,pdo_pgsql"

./bin/spc build:libs "libxml2"  --cc=clang --cxx=clang++ --debug

./bin/spc build:libs "postgresql"  --cc=clang --cxx=clang++ --debug

./bin/spc build "${EXTENSIONS}" --build-cli --cc=clang --cxx=clang++ --debug
exit 0
./bin/spc build:libs "libiconv,libxml2,zstd,zlib,openssl,ncurses,readline,icu,postgresql"  --cc=clang --cxx=clang++ --debug
exit 0

# ./bin/spc build "${EXTENSIONS}" --build-cli --cc=gcc --cxx=g++  --debug
