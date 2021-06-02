#!/bin/sh

VER_PHP="$1"

function php_compile_args() {
    _php_arg="--prefix=/app/php-dist"
    _php_arg="$_php_arg --disable-all"
    _php_arg="$_php_arg --enable-shared=no"
    _php_arg="$_php_arg --enable-static=yes"
    _php_arg="$_php_arg --enable-inline-optimization"
    _php_arg="$_php_arg --with-layout=GNU"
    _php_arg="$_php_arg --enable-calendar"
    _php_arg="$_php_arg --enable-ctype"
    _php_arg="$_php_arg --enable-filter"
    _php_arg="$_php_arg --enable-openssl"
    _php_arg="$_php_arg --enable-bcmath"
    _php_arg="$_php_arg --enable-pcntl"
    _php_arg="$_php_arg --enable-openssl"
    _php_arg="$_php_arg --with-openssl"
    _php_arg="$_php_arg --with-iconv"
    _php_arg="$_php_arg --enable-mbstring"
    _php_arg="$_php_arg --enable-phar"
    _php_arg="$_php_arg --enable-pdo"
    _php_arg="$_php_arg --with-pdo-mysql=mysqlnd"
    _php_arg="$_php_arg --enable-sockets"
    _php_arg="$_php_arg --enable-swoole"
    _php_arg="$_php_arg --enable-inotify"
    _php_arg="$_php_arg --enable-redis"
    _php_arg="$_php_arg --disable-redis-session"
    _php_arg="$_php_arg --enable-simplexml"
    _php_arg="$_php_arg --enable-dom"
    _php_arg="$_php_arg --enable-xml"
    _php_arg="$_php_arg --enable-xmlwriter"
    _php_arg="$_php_arg --enable-xmlreader"
    _php_arg="$_php_arg --with-zlib"
    _php_arg="$_php_arg --enable-posix"
    _php_arg="$_php_arg --enable-mysqlnd"
    _php_arg="$_php_arg --enable-tokenizer"
    _php_arg="$_php_arg --with-curl"
    _php_arg="$_php_arg --with-pear=no"
    _php_arg="$_php_arg --disable-cgi"
    _php_arg="$_php_arg --disable-phpdbg"

    for loop in $(cat extension.txt)
    do
        case $loop in
        swoole) 
        esac
    done

    case $1 in
    7.3.*|7.2.*)
        _php_arg="$_php_arg --with-gd"
        _php_arg="$_php_arg --enable-libxml"
        _php_arg="$_php_arg --enable-hash"
        _php_arg="$_php_arg --enable-json"
        ;;
    7.4.*)
        _php_arg="$_php_arg --enable-gd"
        _php_arg="$_php_arg --with-libxml"
        _php_arg="$_php_arg --enable-json"
        ;;
    8.*)
        _php_arg="$_php_arg --enable-gd"
        _php_arg="$_php_arg --with-libxml"
        ;;
    esac
    echo $_php_arg
}

function before_configure() {
    for loop in $(cat extension.txt)
    do
        case $loop in
        swoole) 
        esac
    done
}

cd php-$VER_PHP && \
    before_configure && \
    ./buildconf --force && \
    ./configure LDFLAGS=-static $(php_compile_args $VER_PHP) && \
    after_configure && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "Makefile" && \
    if [ "$(cat extension.txt | grep swoole)" != "" ]; then  sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "ext/swoole/include/swoole.h" && \
