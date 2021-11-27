#!/bin/sh

# Here are 3 steps in configuration of extensions
# before_configure
# in_configure
# after_configure

self_dir=$(cd "$(dirname "$0")";pwd)
php_dir=$(find $self_dir/source -name "php-*" -type d | tail -n1)

function do_xml_compiler() {
    cd $self_dir/source/liblzma-* && \
        ./configure && \
        make -j4 && \
        make install && \
        echo "liblzma compiled!" && sleep 2s && \
        cd ../libxml2-* && \
        ./configure --prefix=/usr --with-lzma --without-python && \
        make -j4 && \
        make install && \
        echo "libxml2 compiled!" && sleep 2s
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
        make -j4 && \
        make install && \
        echo "curl compiled!" && \
        cat "$self_dir/ac_override_1" "$php_dir/ext/curl/config.m4" "$self_dir/ac_override_2" > /tmp/aa && \
        mv /tmp/aa "$php_dir/ext/curl/config.m4"
}

function do_copy_extension() {
    ext_dir=$(find $self_dir/source -name "$1-*" -type d | tail -n1)
    mv $ext_dir $php_dir/ext/$1
    if [ $? != 0 ]; then
        echo "Compile error! ext: $1, ext_dir=$ext_dir"
        exit 1
    fi
}

function check_before_configure() {
    list=$(cat "$self_dir/extensions.txt" | grep -v "^#" | grep -v "^$")
    xml_sign="no"
    for loop in $list
    do
        case $loop in
        bcmath) ;;
        calendar) ;;
        ctype) ;;
        filter) ;;
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
        phar) ;;
        posix) ;;
        sockets) ;;
        sqlite3) ;;
        tokenizer) ;;
        zlib) ;;
        curl)
            if [ ! -f "$self_dir/source/.curl_compiled" ]; then
                do_curl_compiler
                touch "$self_dir/source/.curl_compiled"
            fi
            if [ $? != 0 ]; then
                echo "Compile curl error!"
                exit 1
            fi
            ;;
        dom|xml|libxml|xmlreader|xmlwriter|simplexml)
            if [ "$xml_sign" = "no" ]; then
                if [ ! -f "$self_dir/source/.xml_compiled" ]; then
                    do_xml_compiler
                    touch "$self_dir/source/.xml_compiled"
                fi
                if [ $? != 0 ]; then
                echo "Compile xml error!"
                exit 1
            fi
                xml_sign="yes"
            fi
            ;;
        inotify) do_copy_extension inotify ;;
        redis) do_copy_extension redis ;;
        swoole) do_copy_extension swoole ;;
        mongodb) do_copy_extension mongodb ;;
        event)
            PHP_EVENT='                     PHP_EVENT_PHP_VERSION=$PHP_VERSION                           '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_EVENT_PHP_VERSION_ID=$PHP_VERSION_ID                     '
            PHP_EVENT="$PHP_EVENT"'\n    if test -z "$PHP_EVENT_PHP_VERSION"; then                    '
            PHP_EVENT="$PHP_EVENT"'\n      AC_MSG_ERROR(\[unknown source with no php version\])         '
            PHP_EVENT="$PHP_EVENT"'\n      PHP_EVENT_SUBDIR="."                                       '
            PHP_EVENT="$PHP_EVENT"'\n    fi                                                           '
            PHP_EVENT="$PHP_EVENT"'\n    if test "$PHP_EVENT_PHP_VERSION_ID" -ge "80000"; then        '
            PHP_EVENT="$PHP_EVENT"'\n      PHP_EVENT_SUBDIR=php8                                      '
            PHP_EVENT="$PHP_EVENT"'\n      AC_MSG_RESULT(\[PHP 8.x\])                                   '
            PHP_EVENT="$PHP_EVENT"'\n    elif test "$PHP_EVENT_PHP_VERSION_ID" -ge "70000"; then      '
            PHP_EVENT="$PHP_EVENT"'\n      PHP_EVENT_SUBDIR=php7                                      '
            PHP_EVENT="$PHP_EVENT"'\n      AC_MSG_RESULT(\[PHP 7.x\])                                   '
            PHP_EVENT="$PHP_EVENT"'\n    elif test "$PHP_EVENT_PHP_VERSION_ID" -ge "50000"; then      '
            PHP_EVENT="$PHP_EVENT"'\n      PHP_EVENT_SUBDIR=php5                                      '
            PHP_EVENT="$PHP_EVENT"'\n      AC_MSG_RESULT(\[PHP 5.x\])                                   '
            PHP_EVENT="$PHP_EVENT"'\n    else                                                         '
            PHP_EVENT="$PHP_EVENT"'\n      AC_MSG_ERROR(\[unknown source lol\])                         '
            PHP_EVENT="$PHP_EVENT"'\n      PHP_EVENT_SUBDIR="."                                       '
            PHP_EVENT="$PHP_EVENT"'\n    fi                                                           '
            PHP_EVENT="$PHP_EVENT"'\n    echo PHP_EXT_SRCDIR(event)\/$PHP_EVENT_SUBDIR                                                           '
            PHP_EVENT="$PHP_EVENT"'\n    echo PHP_EXT_BUILDDIR(event)\/$PHP_EVENT_SUBDIR                                                           '
            PHP_EVENT="$PHP_EVENT"'\n    if test "$PHP_EVENT_SUBDIR" -ne "."; then                    '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_BUILD_DIR(PHP_EXT_SRCDIR(event)\/$PHP_EVENT_SUBDIR, 1)       '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_BUILD_DIR(PHP_EXT_SRCDIR(event)\/$PHP_EVENT_SUBDIR\/classes, 1)       '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_BUILD_DIR(PHP_EXT_SRCDIR(event)\/$PHP_EVENT_SUBDIR\/src, 1)       '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_INCLUDE(PHP_EXT_SRCDIR(event)\/\[$PHP_EVENT_SUBDIR\])            '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_INCLUDE(PHP_EXT_SRCDIR(event)\/\[$PHP_EVENT_SUBDIR\/classes\])            '
            PHP_EVENT="$PHP_EVENT"'\n    PHP_ADD_INCLUDE(PHP_EXT_SRCDIR(event)\/\[$PHP_EVENT_SUBDIR\/src\])            '
            PHP_EVENT="$PHP_EVENT"'\n    fi                                                           '

            do_copy_extension event && \
                sed -ie 's/PHP_EVENT_SUBDIR="."//g' $php_dir/ext/event/config.m4 && \
                sed -ie 's/AC_MSG_ERROR(\[unknown source\])/'"$PHP_EVENT"'/g' $php_dir/ext/event/config.m4
            if [ $? != 0 ]; then
                exit 1
            fi
            ;;
        esac
    done
}

function check_in_configure() {
    php_configure=""
    list=$(cat "$self_dir/extensions.txt" | sed 's/#.*//g' | sed -e 's/[ ]*$//g' | grep -v "^\s*$")
    for loop in $list
    do
        case $loop in
        bcmath)             php_configure="$php_configure --enable-bcmath" ;;
        calendar)           php_configure="$php_configure --enable-calendar" ;;
        ctype)              php_configure="$php_configure --enable-ctype" ;;
        curl)               php_configure="$php_configure --with-curl" ;;
        dom)                php_configure="$php_configure --enable-dom" ;;
        event)              php_configure="$php_configure --with-event-core --with-event-extra --with-event-openssl --with-event-extra --disable-event-sockets" ;;
        filter)             php_configure="$php_configure --enable-filter" ;;
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
        simplexml)          php_configure="$php_configure --enable-simplexml" ;;
        sockets)            php_configure="$php_configure --enable-sockets" ;;
        sqlite3)            php_configure="$php_configure --with-sqlite3" ;;
        
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
        *)
            echo "Unsupported extension '$loop' !" >&2
            exit 1
            ;;
        esac
    done
    echo $php_configure
}

function check_after_configure() {
    list=$(cat "$self_dir/extensions.txt" | grep -v "^#" | grep -v "^$")
    for loop in $list
    do
        case $loop in
        swoole)
            sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "$php_dir/ext/swoole/include/swoole.h"
            ;;
        esac
    done
}

$1 $2
