#!/bin/sh

_script_ver="1.2.1"
_use_backup="no"

_home_dir=$(pwd)"/" 
_source_dir="$_home_dir""source"
_opt_dir="$_home_dir""opt"

function download_file() {
  downloader="wget"
  type wget >/dev/null 2>&1 || { downloader="curl"; }
  if [ "$downloader" = "wget" ]; then
    _down_prefix="O"
  else
    _down_prefix="o"
  fi
  _down_symbol=0
  if [ ! -f "$2" ]; then
    $downloader "$1" -$_down_prefix "$2" >/dev/null 2>&1 && \
    echo "$1 完成！ $2" && _down_symbol=1
  else
    echo "$2 已存在！" && _down_symbol=1
  fi
  if [ $_down_symbol == 0 ]; then
    echo "下载 $1 失败！请检查网络连接！"
    rm -rf "$2"
    return 1
  fi
  return 0
}

# 获取要下载的源码的版本号
function lib_ver() {
    case $1 in
    "phpver"|"php") echo "7.3.28" ;;
    "swoole")       echo "4.6.7" ;;
    "hash")         echo "1.5" ;;
    "inotify")      echo "3.0.0" ;;
    "redis")        echo "5.3.4" ;;
    "libxml2")      echo "2.9.10" ;;
    "curl")         echo "7.76.1" ;;
    "liblzma")      echo "master" ;;
    *)              echo "unknown" ;;
    esac
}

# 获取解压后的源码根目录
function lib_x_dirname() {
    case $1 in
        "php"|"swoole"|"hash"|"inotify"|"redis"|"libxml2"|"curl")
            if [ "$2" = "file" ]; then _name_prefix=".tar.gz"; else _name_prefix=""; fi
            echo "$1-$(lib_ver $1)$_name_prefix"
            ;;
        "liblzma")
            if [ "$_use_backup" = "yes" ]; then 
                if [ "$2" = "file" ]; then _name_prefix=".zip"; else _name_prefix=""; fi
                echo "$1-$(lib_ver $1)$_name_prefix" 
            else
                if [ "$2" = "file" ]; then _name_prefix=".tar.gz"; else _name_prefix=""; fi
                echo "$1""$_name_prefix"
            fi
            ;;
        *)
            echo "unknown" 
            ;;
    esac
}

# 获取要下载的源码的链接地址
function lib_download_link() {
    if [ "$_use_backup" = "yes" ]; then
        case $1 in
        "php")          echo "https://www.php.net/distributions/php-$(lib_ver $1).tar.gz" ;;
        "swoole")       echo "https://pecl.php.net/get/swoole-$(lib_ver $1).tgz" ;;
        "hash")         echo "https://pecl.php.net/get/hash-$(lib_ver $1).tgz" ;;
        "inotify")      echo "https://pecl.php.net/get/inotify-$(lib_ver $1).tgz" ;;
        "redis")        echo "https://pecl.php.net/get/redis-$(lib_ver $1).tgz" ;;
        "libxml2")      echo "http://xmlsoft.org/sources//libxml2-$(lib_ver $1).tar.gz" ;;
        "liblzma")      echo "https://github.com/kobolabs/liblzma/archive/refs/heads/master.zip" ;;
        "curl")         echo "https://curl.haxx.se/download/curl-$(lib_ver $1).tar.gz" ;;
        *)              echo "unknown" ;;
        esac
    else 
        case $1 in
        "php")          echo "http://mirrors.zhamao.xin/php/php-$(lib_ver $1).tar.gz" ;;
        "swoole")       echo "http://mirrors.zhamao.xin/pecl/swoole-$(lib_ver $1).tgz" ;;
        "hash")         echo "http://mirrors.zhamao.xin/pecl/hash-$(lib_ver $1).tgz" ;;
        "inotify")      echo "http://mirrors.zhamao.xin/pecl/inotify-$(lib_ver $1).tgz" ;;
        "redis")        echo "http://mirrors.zhamao.xin/pecl/redis-$(lib_ver $1).tgz" ;;
        "libxml2")      echo "https://dl.zhamao.me/libxml2/libxml2-$(lib_ver $1).tar.gz" ;;
        "liblzma")      echo "https://dl.zhamao.me/liblzma/liblzma.tar.gz" ;;
        "curl")         echo "https://dl.zhamao.me/curl/curl-$(lib_ver $1).tar.gz" ;;
        *)              echo "unknown" ;;
        esac
    fi
}

# 获取下载后源码包的解压命令
function lib_x_cmd() {
    case $1 in
        "php"|"swoole"|"hash"|"inotify"|"redis"|"libxml2"|"curl")
            _x_cmd="tar"
            ;;
        "liblzma") if [ "$_use_backup" = "yes" ]; then _x_cmd="unzip"; else _x_cmd="tar"; fi ;;
        *) _x_cmd="unknown" ;;
    esac
    case $2 in
    "cmd")
        echo $_x_cmd
        ;;
    "file-prefix")
        case $_x_cmd in
        "tar") echo "-xf" ;;
        "unzip") echo "" ;;
        esac
        ;;
    "out-prefix")
        case $_x_cmd in
        "tar") echo "-C" ;;
        "unzip") echo "-d" ;;
        esac
        ;;
    esac
}

_curl_override_1='
AC_DEFUN([PHP_CHECK_LIBRARY], [
  $3
])
'

_curl_override_2='
AC_DEFUN([PHP_CHECK_LIBRARY], [
  save_old_LDFLAGS=$LDFLAGS
  ac_stuff="$5"

  save_ext_shared=$ext_shared
  ext_shared=yes
  PHP_EVAL_LIBLINE([$]ac_stuff, LDFLAGS)
  AC_CHECK_LIB([$1],[$2],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    $3
  ],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    unset ac_cv_lib_$1[]_$2
    $4
  ])dnl
])
'

function lib_x() {
    $(lib_x_cmd $1 cmd) $(lib_x_cmd $1 file-prefix) "$_source_dir/$(lib_x_dirname $1 file)" $(lib_x_cmd $1 out-prefix) "$_source_dir/"
}

function lib_move_ext() {
    _src_dir="$_source_dir/$(lib_x_dirname $1)"
    _dst_dir="$_source_dir/$(lib_x_dirname php)/ext/$1"
    mv $_src_dir $_dst_dir
}

function download_all() {
    mkdir "$_source_dir" > /dev/null 2>&1

    for loop in "php" "swoole" "inotify" "hash" "redis" "libxml2" "liblzma" "curl"
    do
        echo "正在下载 $loop 源码 ..."
        download_file $(lib_download_link $loop) "$_source_dir/$(lib_x_dirname $loop file)" || { exit 1; } &
    done

    wait
}

function compile_liblzma() {
    if [ -f "$_opt_dir/liblzma/lib/liblzma.so" ]; then
      echo "已编译 liblzma！" && return
    fi
    tar -xf "$_source_dir/$(lib_x_dirname liblzma file)" -C "$_source_dir/" && \
        cd "$_source_dir/$(lib_x_dirname liblzma)" && \
        ./configure --prefix="$_opt_dir/liblzma" && \
        make -j4 && \
        make install && \
        echo "编译 liblzma 完成！"
}

function compile_curl() {
    if [ -f "$_opt_dir/curl/bin/curl" ]; then
      echo "已编译 curl！" && return
    fi
    lib_x curl && \
        cd "$_source_dir/$(lib_x_dirname curl)" && \
        CC=gcc CXX=g++ CFLAGS=-fPIC CPPFLAGS=-fPIC ./configure --prefix="$_opt_dir/curl" \
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
        echo "编译 curl 完成！"
}

function compile_libxml2() {
    if [ -f "$_opt_dir/libxml2/lib/libxml2.so" ]; then
      echo "已编译 libxml2！" && return
    fi
    lib_x libxml2 && \
        cd "$_source_dir/$(lib_x_dirname libxml2)" && \
        ./configure --with-lzma="$_opt_dir/liblzma" --prefix="$_opt_dir/libxml2" --exec-prefix="$_opt_dir/libxml2" --without-python && \
        make -j4 && \
        make install && \
        echo "编译 libxml2 完成！"
}

function php_get_configure_args() {
    _php_arg="--prefix=$_home_dir""php-dist"
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
    _php_arg="$_php_arg --with-openssl-dir=/usr"
    _php_arg="$_php_arg --enable-pcntl"
    _php_arg="$_php_arg --enable-openssl"
    _php_arg="$_php_arg --with-openssl"
    _php_arg="$_php_arg --with-iconv"
    _php_arg="$_php_arg --enable-json"
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

    case $(lib_ver php) in
    7.3.*|7.2.*)
        _php_arg="$_php_arg --with-gd"
        _php_arg="$_php_arg --enable-libxml"
        _php_arg="$_php_arg --with-libxml-dir=$_opt_dir/libxml2"
        _php_arg="$_php_arg --enable-hash"
        ;;
    *)
        _php_arg="$_php_arg --enable-gd"
        _php_arg="$_php_arg --with-libxml"
        ;;
    esac
    echo $_php_arg
}

function compile_php() {
    echo "正在编译 php ..." && \
        rm -rf "$_source_dir/$(lib_x_dirname php)" && \
        lib_x php && \
        lib_x swoole && \
        lib_move_ext swoole && \
        "$_source_dir/$(lib_x_dirname php)/ext/swoole/clear.sh" && \
        lib_x redis && \
        lib_move_ext redis && \
        lib_x inotify && \
        lib_move_ext inotify && \
        cd "$_source_dir/$(lib_x_dirname php)/" && \
        echo "$_curl_override_1" > "$_home_dir""ac_override_1" && \
        echo "$_curl_override_2" > "$_home_dir""ac_override_2" && \
        cat "$_home_dir""ac_override_1" "ext/curl/config.m4" "$_home_dir""ac_override_2" > /tmp/aa && \
        mv /tmp/aa "ext/curl/config.m4" && \
        rm -rf "$_home_dir""ac_override_1" "$_home_dir""ac_override_2" && \
        PKG_CONFIG_PATH="$PKG_CONFIG_PATH:""$_opt_dir/libxml2/lib/pkgconfig" && \
        PKG_CONFIG_PATH="$PKG_CONFIG_PATH:""$_opt_dir/curl/lib/pkgconfig" && \
        ./buildconf --force && \
        PKG_CONFIG_PATH=$PKG_CONFIG_PATH ./configure LDFLAGS=-static $(php_get_configure_args) && \
        sed -ie 's/-export-dynamic//g' "$_source_dir/$(lib_x_dirname php)/Makefile" && \
        sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "$_source_dir/$(lib_x_dirname php)/Makefile" && \
        sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "$_source_dir/$(lib_x_dirname php)/ext/swoole/include/swoole.h" && \
        make LDFLAGS=-ldl -j4 && \
        make install && \
        strip "$_home_dir""php-dist/bin/php" && \
        cd $_home_dir
}

#apk add g++ pkgconf autoconf nghttp2 libcurl \
#    git gcc zlib-dev libstdc++ ncurses zlib linux-headers \
#    readline make libssl1.1 libxml2 m4 libgcc vim binutils \
#    oniguruma-dev openssl openssl-dev
# 编译必需的
apk add gcc g++ autoconf libstdc++ linux-headers make m4 libgcc binutils ncurses
# php的zlib支持
apk add zlib-dev zlib-static
# php的mbstring支持
apk add oniguruma-dev
# php的openssl支持
apk add openssl-libs-static openssl-dev openssl
# php的gd支持，如果不需要gd则去掉--enable-gd和下面的依赖
apk add libpng-dev libpng-static
# curl的c-ares支持，如果不需要curl则去掉
apk add c-ares-static c-ares-dev


download_all && \
    compile_liblzma && \
    compile_libxml2 && \
    compile_curl && \
    compile_php && \
    echo "完成！见 php-dist/bin/php"

