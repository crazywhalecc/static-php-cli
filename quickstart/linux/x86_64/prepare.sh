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
EOF


./bin/spc build:libs zlib  --debug  --cc=musl-gcc  --cxx=g++
./bin/spc build:libs bzip2 --debug --cc=musl-gcc  --cxx=g++
./bin/spc build:libs libzip  --debug --cc=musl-gcc  --cxx=g++
./bin/spc build:libs libjpeg --debug --cc=musl-gcc  --cxx=g++
./bin/spc build:libs libpng --debug  --cc=musl-gcc  --cxx=g++
./bin/spc build:libs libgif --debug  --cc=musl-gcc  --cxx=g++
./bin/spc build:libs libwebp --debug  --cc=musl-gcc  --cxx=g++
./bin/spc build:libs brotli --debug  --cc=musl-gcc  --cxx=g++
./bin/spc build:libs freetype --debug --cc=musl-gcc  --cxx=g++


# ./bin/spc build "bcmath,tokenizer,pdo,ftp,gd" --cc=clang --cxx=clang++  --debug

# ./bin/spc build gd --debug --cc=clang --cxx=clang++ --debug

# ./bin/spc build gd,zlib  --cc=gcc       --cxx=g++ --build-cli  --debug
./bin/spc build gd,zlib  --cc=musl-gcc  --cxx=g++ --build-cli --debug
# ./bin/spc build gd,zlib  --cc=clang     --cxx=clang++ --build-cli --debug

# musl-gcc/musl-clang（或 gcc-musl/gcc-clang