#!/bin/sh

VER_PHP="$1"

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
    _php_arg="$_php_arg $($self_dir/check-extensions.sh check_in_configure $1)"
    echo $_php_arg
}

php_compile_args && sleep 1s

cd $php_dir && \
    ./buildconf --force && \
    ./configure LDFLAGS=-static $(php_compile_args $VER_PHP) && \
    $self_dir/check-extensions.sh check_after_configure && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "Makefile" && \
    make LDFLAGS="-ldl -llzma -lbz2" -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
    make install && \
    strip $self_dir/php-dist/bin/php
