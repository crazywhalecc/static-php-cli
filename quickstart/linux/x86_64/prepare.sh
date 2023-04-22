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


#./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo,pdo_sqlite,ftp,curl" --cc=gcc --cxx=g++  --debug

./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo,pdo_sqlite,ftp,curl" --cc=clang --cxx=clang++  --debug
