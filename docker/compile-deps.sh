#!/usr/bin/env bash

self_dir=$(cd "$(dirname "$0")";pwd)

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

function do_iconv_compiler() {
    cd $self_dir/source/libiconv-* && \
        ./configure --enable-static=yes --prefix=/usr && \
        make -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
        make install && \
        echo "libiconv compiled!"
}

if [ ! -f "$self_dir/source/.deps-compiled" ]; then
    do_xml_compiler && \
        do_curl_compiler && \
        do_libzip_compiler && \
        do_iconv_compiler && \
        touch "$self_dir/source/.deps-compiled"
else
    echo "Skip compilation for dependencies"
fi
