#!/bin/sh

# Here are 3 steps in configuration of extensions
# before_configure
# in_configure
# after_configure

self_dir=$(cd "$(dirname "$0")";pwd)
php_dir=$(find $self_dir/source -name "php-*" -type d | tail -n1)
test -f "$self_dir/extensions_install.txt" && EXT_LIST_FILE="$self_dir/extensions_install.txt" || EXT_LIST_FILE="$self_dir/extensions.txt"


function do_copy_extension() {
    ext_dir=$(find $self_dir/source -name "*$1-*" -type d | tail -n1)
    mv $ext_dir $php_dir/ext/$1
    if [ $? != 0 ]; then
        echo "Compile error! ext: $1, ext_dir=$ext_dir"
        exit 1
    fi
}

function check_before_configure() {
    list=$(cat "$EXT_LIST_FILE" | grep -v "^#" | grep -v "^$" | grep -v "^\^")
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
        protobuf) 
            do_copy_extension protobuf
            echo '#ifndef PHP_PROTOBUF_H' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '# define PHP_PROTOBUF_H' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '#ifdef HAVE_CONFIG_H' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '# include "config.h"' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '#endif' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo 'extern zend_module_entry protobuf_module_entry;' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '# define phpext_protobuf_ptr &protobuf_module_entry' >> $php_dir/ext/protobuf/php_protobuf.h && \
            echo '#endif' >> $php_dir/ext/protobuf/php_protobuf.h
            ;;
        readline)
            if [ ! -d "/nom" ]; then
                mkdir /nom
            fi
            mv /usr/lib/libreadline.so* /nom/ && \
                mv /usr/lib/libncurses*.so* /nom
            ;;
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
        mv $self_dir/source/phpmicro $php_dir/sapi/micro && \
            sed -ie 's/#include "php.h"/#include "php.h"\n#define PHP_MICRO_FAKE_CLI 1/g' $php_dir/sapi/micro/php_micro.c
        ;;
    esac
}

function check_in_configure() {
    php_configure=""
    list=$(cat "$EXT_LIST_FILE" | sed 's/#.*//g' | sed 's/\^.*//g' | sed -e 's/[ ]*$//g' | grep -v "^\s*$")
    for loop in $list
    do
        case $loop in
        bcmath)             php_configure="$php_configure --enable-bcmath" ;;
        calendar)           php_configure="$php_configure --enable-calendar" ;;
        ctype)              php_configure="$php_configure --enable-ctype" ;;
        curl)               php_configure="$php_configure --with-curl" ;;
        dom)                php_configure="$php_configure --enable-dom" ;;
        exif)               php_configure="$php_configure --enable-exif" ;;
        event)              php_configure="$php_configure --with-event-libevent-dir=/usr --with-event-core --with-event-extra --with-event-openssl" ;;
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
        protobuf)           php_configure="$php_configure --enable-protobuf" ;;
        readline)           php_configure="$php_configure --with-readline" ;;
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

function finish_compile() {
    if [ -d "/nom" ]; then
        mv /nom/* /usr/lib/ || echo "Empty directory"
        rm -rf /nom/
    fi
}

$1 $2
