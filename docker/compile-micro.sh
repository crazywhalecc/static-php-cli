#!/bin/sh

VER_PHP="$1"
USE_BACKUP="$2"

self_dir=$(cd "$(dirname "$0")";pwd)
php_dir=$(find $self_dir/source -name "php-$VER_PHP" -type d | tail -n1)

function php_compile_args() {
    _php_arg="--prefix=$self_dir/php-dist"
    _php_arg="$_php_arg --disable-all"
    _php_arg="$_php_arg --enable-shared=no"
    _php_arg="$_php_arg --enable-static=yes"
    _php_arg="$_php_arg --enable-inline-optimization"
    _php_arg="$_php_arg --with-layout=GNU"
    _php_arg="$_php_arg --with-pear=no"
    _php_arg="$_php_arg --disable-cgi"
    _php_arg="$_php_arg --disable-phpdbg"
    _php_arg="$_php_arg --with-ffi"
    _php_arg="$_php_arg --enable-micro"
    _php_arg="$_php_arg $($self_dir/check-extensions.sh check_in_configure $1)"
    echo $_php_arg
}

php_compile_args && sleep 1s

test "$USE_BACKUP" = "no" && GITHUB_ADDR="fgit.zhamao.me" || GITHUB_ADDR="github.com"

cd $php_dir && \
    git clone https://$GITHUB_ADDR/dixyes/phpmicro.git --depth=1 sapi/micro && \
    ./buildconf --force && \
    ./configure LDFLAGS=-static $(php_compile_args $VER_PHP) && \
    $self_dir/check-extensions.sh check_after_configure && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "Makefile" && \
    sed -ie 's/-o $(SAPI_MICRO_PATH)/-all-static -o $(SAPI_MICRO_PATH)/g' "Makefile" && \
    #sed -ie 's/$(PHP_GLOBAL_OBJS) $(PHP_BINARY_OBJS) $(PHP_MICRO_OBJS)/$(PHP_GLOBAL_OBJS:.lo=.o) $(PHP_BINARY_OBJS:.lo=.o) $(PHP_MICRO_OBJS:.lo=.o)/g' "Makefile" && \
    sed -ie 's/$(EXTRA_LIBS:-lresolv=-Wl,-Bstatic,-lresolv,-Bdynamic)/$(EXTRA_LIBS)/g' "Makefile" && \
    make LDFLAGS="-ldl" micro -j$(cat /proc/cpuinfo | grep processor | wc -l)
    #make install
    #strip $self_dir/php-dist/bin/php
