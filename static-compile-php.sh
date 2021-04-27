#!/bin/bash

function downloadIt() {
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
    echo "$1 完成！" && _down_symbol=1
  else
    echo "$1 已存在！" && _down_symbol=1
  fi
  if [ $_down_symbol == 0 ]; then
    echo "失败！请检查网络连接！"
    rm -rf "$2"
    return 1
  fi
  return 0
}

_script_ver="1.1.0"

_php_ver="7.4.16"
_swoole_ver="4.6.6"
_redis_ver="5.3.4"
_libxml2_ver="2.9.10"
_curl_ver="7.76.1"

# 默认编译在脚本当前目录
_home_dir=$(pwd)"/"

# PHP使用国内的搜狐镜像
_php_down_link="http://mirrors.sohu.com/php/php-$_php_ver.tar.gz"
# swoole使用自建的炸毛服务器的分发，因为pecl有时候在国内奇慢
_swoole_down_link="https://dl.zhamao.me/swoole/swoole-$_swoole_ver.tgz"
_swoole_down_link_bak="https://pecl.php.net/get/swoole-$_swoole_ver.tgz"
# phpredis也使用自建的炸毛服务器分发
_redis_down_link="https://dl.zhamao.me/phpredis/redis-$_redis_ver.tgz"
_redis_down_link_bak="https://pecl.php.net/get/redis-$_redis_ver.tgz"
# libxml2也使用自建的服务器
_libxml2_down_link="https://dl.zhamao.me/libxml2/libxml2-$_libxml2_ver.tar.gz"
_libxml2_down_link_bak="http://xmlsoft.org/sources/libxml2-$_libxml2_ver.tar.gz"
# liblzma是自建的服务器，如果需要找原始位置，在GitHub搜索liblzma即可
_liblzma_down_link="https://dl.zhamao.me/liblzma/liblzma.tar.gz"
# curl/libcurl使用自建的服务器，bak是源地址
_curl_down_link="https://dl.zhamao.me/curl/curl-$_curl_ver.tar.gz"
_curl_down_link_bak="https://curl.haxx.se/download/curl-$_curl_ver.tar.gz"

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

function downloadAll() {
    mkdir "$_home_dir""source" > /dev/null 2>&1

    echo "正在下载 php 源码 "$_php_down_link" ..."
    downloadIt $_php_down_link "$_home_dir""source/php.tar.gz" || { exit 1; } &

    #echo "正在下载 openssl 源码 "$_openssl_down_link" ..."
    #downloadIt $_openssl_down_link "$_home_dir""source/openssl.tar.gz" || { exit; } &

    echo "正在下载 swoole 源码 "$_swoole_down_link" ..."
    downloadIt $_swoole_down_link "$_home_dir""source/swoole.tar.gz" || { exit 1; } &

    echo "正在下载 redis 源码 "$_redis_down_link" ..."
    downloadIt $_redis_down_link "$_home_dir""source/redis.tar.gz" || { exit 1; } &

    echo "正在下载 libxml2 源码 ..."
    downloadIt $_libxml2_down_link "$_home_dir""source/libxml2.tar.gz" || { exit 1; } &

    echo "正在下载 liblzma 源码 ..."
    downloadIt $_liblzma_down_link "$_home_dir""source/liblzma.tar.gz" || { exit 1; } &

    echo "正在下载 curl 源码 ..."
    downloadIt $_curl_down_link "$_home_dir""source/curl.tar.gz" || { exit 1; } &

    wait
}

function compileLiblzma() {
    if [ -f "$_home_dir""opt/liblzma/lib/liblzma.so" ]; then
      echo "已编译 liblzma！" && return
    fi
    tar -xf "$_home_dir""source/liblzma.tar.gz" -C "$_home_dir""source/" && \
        cd "$_home_dir""source/liblzma" && \
        ./configure --prefix="$_home_dir""opt/liblzma" && \
        make -j4 && \
        make install && \
        echo "编译 liblzma 完成！"
}

function compileCurl() {
    if [ -f "$_home_dir""opt/curl/bin/curl" ]; then
      echo "已编译 curl！" && return
    fi
    tar -xf "$_home_dir""source/curl.tar.gz" -C "$_home_dir""source/" && \
        cd "$_home_dir""source/curl-""$_curl_ver" && \
        CC=gcc CXX=g++ CFLAGS=-fPIC CPPFLAGS=-fPIC ./configure --prefix="$_home_dir""opt/curl" \
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

function compileLibxml2() {
    if [ -f "$_home_dir""opt/libxml2/lib/libxml2.so" ]; then
      echo "已编译 libxml2！" && return
    fi
    tar -xf "$_home_dir""source/libxml2.tar.gz" -C "$_home_dir""source/" && \
        cd "$_home_dir""source/libxml2-""$_libxml2_ver" && \
        ./configure --with-lzma="$_home_dir""opt/liblzma" --prefix="$_home_dir""opt/libxml2" --exec-prefix="$_home_dir""opt/libxml2" --without-python && \
        make -j4 && \
        make install && \
        echo "编译 libxml2 完成！"
}

function compilePHPWithSwoole() {
    echo "正在编译 php ..." && \
        rm -rf "$_home_dir""source/php-""$_php_ver" && \
        tar -xf "$_home_dir""source/php.tar.gz" -C "$_home_dir""source/" && \
        #git clone --depth=1 https://fgit.zhamao.me/swoole/swoole-src.git "$_home_dir""source/"swoole-$_swoole_ver && \
        tar -xf "$_home_dir""source/swoole.tar.gz" -C "$_home_dir""source/" && \
        mv "$_home_dir""source/swoole-""$_swoole_ver" "$_home_dir""source/php-""$_php_ver/ext/swoole" && \
        "$_home_dir""source/php-""$_php_ver/ext/swoole/clear.sh" && \
        tar -xf "$_home_dir""source/redis.tar.gz" -C "$_home_dir""source/" && \
        mv "$_home_dir""source/redis-""$_redis_ver" "$_home_dir""source/php-""$_php_ver/ext/redis" && \
        cd "$_home_dir""source/php-""$_php_ver/" && \
        echo "$_curl_override_1" > "$_home_dir""ac_override_1" && \
        echo "$_curl_override_2" > "$_home_dir""ac_override_2" && \
        cat "$_home_dir""ac_override_1" "$_home_dir""source/php-""$_php_ver/ext/curl/config.m4" "$_home_dir""ac_override_2" > /tmp/aa && \
        mv /tmp/aa "$_home_dir""source/php-""$_php_ver/ext/curl/config.m4" && \
        ./buildconf --force && \
        PKG_CONFIG_PATH="$PKG_CONFIG_PATH:""$_home_dir""opt/curl/lib/pkgconfig" ./configure LDFLAGS=-static \
            --prefix="$_home_dir""php-dist" \
            --disable-all \
            --enable-shared=no \
            --enable-static=yes \
            --enable-inline-optimization \
            --with-layout=GNU \
            --enable-calendar \
            --enable-ctype \
            --enable-filter \
            --enable-openssl \
            --enable-bcmath \
            --with-openssl-dir="/usr" \
            --enable-pcntl \
            --enable-openssl \
            --with-openssl \
            --with-iconv \
            --enable-json \
            --enable-mbstring \
            --enable-phar \
            --enable-pdo \
            --with-pdo-mysql=mysqlnd \
            --enable-sockets \
            --enable-swoole \
            --enable-gd \
            --enable-redis \
            --disable-redis-session \
            --enable-simplexml \
            --enable-dom \
            --with-libxml="$_home_dir""opt/libxml2" \
            --enable-xml \
            --enable-xmlwriter \
            --enable-xmlreader \
            --with-zlib \
            --enable-posix \
            --enable-mysqlnd \
            --enable-tokenizer \
            --with-curl="$_home_dir""opt/curl" \
            --with-pear=no \
            --disable-pear \
            --disable-cgi \
            --disable-phpdbg && \
        sed -ie 's/-export-dynamic//g' "$_home_dir""source/php-""$_php_ver/Makefile" && \
        sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "$_home_dir""source/php-""$_php_ver/Makefile" && \
        sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "$_home_dir""source/php-""$_php_ver/ext/swoole/include/swoole.h" && \
        make LDFLAGS=-ldl -j4 && \
        make install && \
        strip "$_home_dir""php-dist/bin/php"
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

downloadAll && \
    compileLiblzma && \
    compileLibxml2 && \
    compileCurl && \
    compilePHPWithSwoole && \
    echo "完成！见 php-dist/bin/php"

