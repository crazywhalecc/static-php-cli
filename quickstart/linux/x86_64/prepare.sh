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

# ./bin/spc fetch --all --debug

./bin/spc list-ext


#./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo,pdo_sqlite,ftp,curl" --cc=gcc --cxx=g++  --debug

#./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo,pdo_sqlite,ftp,curl" --cc=clang --cxx=clang++  --debug

cc=musl-gcc
cxx=g++
:<<'EOF'
  cc=clang
  cxx=clang++
  cc=musl-gcc
  cxx=g++
EOF


./bin/spc build:libs zlib  --debug  --cc=clang --cxx=clang++
./bin/spc build:libs bzip2 --debug --cc=clang --cxx=clang++
./bin/spc build:libs libzip  --debug --cc=clang --cxx=clang++
./bin/spc build:libs libjpeg --debug --cc=clang --cxx=clang++
./bin/spc build:libs libpng --debug  --cc=clang --cxx=clang++
./bin/spc build:libs libgif --debug  --cc=clang --cxx=clang++
./bin/spc build:libs libwebp --debug  --cc=clang --cxx=clang++
./bin/spc build:libs brotli --debug  --cc=clang --cxx=clang++
./bin/spc build:libs freetype --debug --cc=clang --cxx=clang++


# ./bin/spc build "bcmath,tokenizer,pdo,ftp,gd" --build-cli --cc=clang --cxx=clang++  --debug



## debian

# ./bin/spc build gd,zlib  --cc=musl-gcc  --cxx=g++ --build-cli --debug

## alpine
# ./bin/spc build gd,zlib  --cc=clang     --cxx=clang++ --build-cli --debug
# ./bin/spc build gd,zlib  --cc=gcc       --cxx=g++ --build-cli  --debug



# musl-gcc/musl-clang（或 gcc-musl/gcc-clang