#!/bin/sh

# Here are 3 steps in configuration of extensions
# before_configure
# in_configure
# after_configure

self_dir=$(cd "$(dirname "$0")";pwd)
php_dir=$(find $self_dir/source -name "php-*" -type d | tail -n1)
test -f "$self_dir/extensions_install.txt" && EXT_LIST_FILE="$self_dir/extensions_install.txt" || EXT_LIST_FILE="$self_dir/extensions.txt"

function do_xml_compiler() {
    cd $self_dir/source/xz-* && \
        ./configure --enable-static=yes  && \
        make -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
        make install && \
        echo "xz compiled!" && \
        cd ../libxml2-* && \
        ./configure --prefix=/usr --with-lzma --without-python && \
        make -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
        make install && \
        echo "libxml2 compiled!"
}

function do_libzip_compiler() {
    cd $self_dir/source/libzip-* && \
        mkdir build && \
        cd build && \
        cmake -DBUILD_SHARED_LIBS=no .. -Wno-dev -DENABLE_BZIP2=no -DENABLE_LZMA=no && \
        make LDFLAGS="-llzma -lbz2" -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
        make install && \
        echo "libzip compiled!"
}

function do_curl_compiler() {
    cd $self_dir/source/curl-* && \
        CC=gcc CXX=g++ CFLAGS=-fPIC CPPFLAGS=-fPIC ./configure \
            --without-nghttp2 \
            --with-ssl=/usr \
            --with-pic=pic \
            --enable-ipv6 \
            --enable-shared=no \
            --without-libidn2 \
            --disable-ldap \
            --without-libpsl \
            --without-lber \
            --enable-ares && \
        make -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
        make install && \
        echo "curl compiled!"
}

function do_copy_extension() {
    ext_dir=$(find $self_dir/source -name "*$1-*" -type d | tail -n1)
    mv $ext_dir $php_dir/ext/$1
    if [ $? != 0 ]; then
        echo "Compile error! ext: $1, ext_dir=$ext_dir"
        exit 1
    fi
}

function check_before_configure() {
    list=$(cat "$EXT_LIST_FILE" | grep -v "^#" | grep -v "^$")
    xml_sign="no"
    for loop in $list
    do
        case $loop in
        bcmath) ;;
        calendar) ;;
        ctype) ;;
        exif) ;;
        filter) ;;
        fileinfo) ;;
        gd) ;;
        hash) ;;
        iconv) ;;
        json) ;;
        mbstring) ;;
        mysqlnd) ;;
        openssl) ;;
        pcntl) ;;
        pdo) ;;
        pdo_mysql) ;;
        pdo_sqlite) ;;
        phar) ;;
        posix) ;;
        shmop) ;;
        sockets) ;;
        sqlite3) ;;
        tokenizer) ;;
        zlib) ;;
        zip) ;;
        curl) cat "$self_dir/ac_override_1" "$php_dir/ext/curl/config.m4" "$self_dir/ac_override_2" > /tmp/aa && mv /tmp/aa "$php_dir/ext/curl/config.m4" ;;
        dom|xml|libxml|xmlreader|xmlwriter|simplexml|soap) ;;
        inotify) do_copy_extension inotify ;;
        redis) do_copy_extension redis ;;
        swoole) do_copy_extension swoole ;;
        mongodb) do_copy_extension mongodb ;;
        event) do_copy_extension event ;;
        esac
    done
    case $1 in
    8.*)
        mv $self_dir/source/micro $php_dir/sapi/ && \
            sed -ie 's/#include "php.h"/#include "php.h"\n#define PHP_MICRO_FAKE_CLI 1/g' $php_dir/sapi/micro/php_micro.c
        ;;
    esac
}

function check_in_configure() {
    php_configure=""
    list=$(cat "$EXT_LIST_FILE" | sed 's/#.*//g' | sed -e 's/[ ]*$//g' | grep -v "^\s*$")
    for loop in $list
    do
        case $loop in
        bcmath)             php_configure="$php_configure --enable-bcmath" ;;
        calendar)           php_configure="$php_configure --enable-calendar" ;;
        ctype)              php_configure="$php_configure --enable-ctype" ;;
        curl)               php_configure="$php_configure --with-curl" ;;
        dom)                php_configure="$php_configure --enable-dom" ;;
        exif)               php_configure="$php_configure --enable-exif" ;;
        event)              php_configure="$php_configure --with-event-core --with-event-extra --with-event-openssl" ;;
        filter)             php_configure="$php_configure --enable-filter" ;;
        fileinfo)           php_configure="$php_configure --enable-fileinfo" ;;
        gd)
            case $1 in
            7.3.*|7.2.*)    php_configure="$php_configure --with-gd" ;;
            7.4.*|8.*)      php_configure="$php_configure --enable-gd" ;;
            esac
            ;;
        hash)
            case $1 in
            7.3.*|7.2.*)    php_configure="$php_configure --enable-hash" ;;
            esac
            ;;
        iconv)              php_configure="$php_configure --with-iconv" ;;
        inotify)            php_configure="$php_configure --enable-inotify" ;;
        json)
            case $1 in
            7.*)            php_configure="$php_configure --enable-json" ;;
            esac
            ;;
        libxml)
            case $1 in
            7.3.*|7.2.*)    php_configure="$php_configure --enable-libxml" ;;
            7.4.*|8.*)      php_configure="$php_configure --with-libxml" ;;
            esac
            ;;
        mbstring)           php_configure="$php_configure --enable-mbstring" ;;
        mongodb)            php_configure="$php_configure --enable-mongodb" ;;
        mysqlnd)            php_configure="$php_configure --enable-mysqlnd" ;;
        openssl)            php_configure="$php_configure --with-openssl --with-openssl-dir=/usr" ;;
        pcntl)              php_configure="$php_configure --enable-pcntl" ;;
        pdo)                php_configure="$php_configure --enable-pdo" ;;
        pdo_mysql)          php_configure="$php_configure --with-pdo-mysql=mysqlnd" ;;
        phar)               php_configure="$php_configure --enable-phar" ;;
        posix)              php_configure="$php_configure --enable-posix" ;;
        redis)              php_configure="$php_configure --enable-redis --disable-redis-session" ;;
        shmop)              php_configure="$php_configure --enable-shmop" ;;
        simplexml)          php_configure="$php_configure --enable-simplexml" ;;
        sockets)            php_configure="$php_configure --enable-sockets" ;;
        soap)               php_configure="$php_configure --enable-soap" ;;
        sqlite3)            php_configure="$php_configure --with-sqlite3" ;;
        pdo_sqlite)         php_configure="$php_configure --with-pdo-sqlite" ;;
        
        swoole)
            php_configure="$php_configure --enable-swoole"
            have_openssl=$(echo $list | grep openssl)
            if [ "$have_openssl" != "" ]; then
                php_configure="$php_configure --enable-openssl --with-openssl --with-openssl-dir=/usr"
            fi
            have_hash=$(echo $list | grep hash)
            if [ "$have_hash" = "" ]; then
                case $1 in
                7.3.*|7.2.*)    php_configure="$php_configure --enable-hash" ;;
                esac
            fi
            ;;
        tokenizer)          php_configure="$php_configure --enable-tokenizer" ;;
        xml)                php_configure="$php_configure --enable-xml" ;;
        xmlreader)          php_configure="$php_configure --enable-xmlreader" ;;
        xmlwriter)          php_configure="$php_configure --enable-xmlwriter" ;;
        zlib)               php_configure="$php_configure --with-zlib" ;;
        zip)                php_configure="$php_configure --with-zip" ;;
        *)
            echo "Unsupported extension '$loop' !" >&2
            exit 1
            ;;
        esac
    done
    case $1 in
    8.*) php_configure="$php_configure --with-ffi --enable-micro=all-static" ;;
    esac
    echo $php_configure
}

function check_after_configure() {
    list=$(cat "$EXT_LIST_FILE" | grep -v "^#" | grep -v "^$")
    for loop in $list
    do
        case $loop in
        swoole)
            sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "$php_dir/ext/swoole/include/swoole.h"
            sed -ie 's/strcmp("cli", sapi_module.name) == 0/strcmp("cli", sapi_module.name) == 0 || strcmp("micro", sapi_module.name) == 0/g' "$php_dir/ext/swoole/ext-src/php_swoole.cc"
            ;;
        esac
    done
    case $1 in
    8.*) sed -ie 's/$(EXTRA_LIBS:-lresolv=-Wl,-Bstatic,-lresolv,-Bdynamic)/$(EXTRA_LIBS)/g' "$php_dir/Makefile" ;;
    esac
}

$1 $2
