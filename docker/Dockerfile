from alpine:latest

ENV SCRIPT_VERSION=1.3.0
ENV USE_BACKUP=no
ENV HOME_DIR=/
ENV SOURCE_DIR=/source
ENV SOFT_DIR=/usr

ENV VER_PHP=7.4.19
ENV VER_SWOOLE=4.6.7
ENV VER_HASH=1.5
ENV VER_INOTIFY=3.0.0
ENV VER_REDIS=5.3.4
ENV VER_LIBXML2=2.9.10
ENV VER_CURL=7.77.0
ENV VER_LIBLZMA=master
ENV VER_LIBZIP=1

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories

# 编译必需的
RUN apk add cmake gcc g++ autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses
# php的zlib支持
RUN apk add zlib-dev zlib-static
# php的mbstring支持
RUN apk add oniguruma-dev
# php的openssl支持
RUN apk add openssl-libs-static openssl-dev openssl
# php的gd支持，如果不需要gd则去掉--enable-gd和下面的依赖
RUN apk add libpng-dev libpng-static
# curl的c-ares支持，如果不需要curl则去掉
RUN apk add c-ares-static c-ares-dev

RUN mkdir /app

WORKDIR /app

COPY ac_override_1 /tmp/
COPY ac_override_2 /tmp/
COPY php-arg.sh /tmp/

RUN mkdir downloads && mkdir source && cd downloads 

RUN wget "http://mirrors.zhamao.xin/php/php-${VER_PHP}.tar.gz" -O php.tar.gz
RUN wget "http://mirrors.zhamao.xin/pecl/swoole-${VER_SWOOLE}.tgz" -O swoole.tar.gz
RUN wget "http://mirrors.zhamao.xin/pecl/hash-${VER_HASH}.tgz" -O hash.tar.gz
RUN wget "http://mirrors.zhamao.xin/pecl/inotify-${VER_INOTIFY}.tgz" -O inotify.tar.gz
RUN wget "http://mirrors.zhamao.xin/pecl/redis-${VER_REDIS}.tgz" -O redis.tar.gz
RUN wget "https://dl.zhamao.me/libxml2/libxml2-${VER_LIBXML2}.tar.gz" -O libxml2.tar.gz
RUN wget "https://dl.zhamao.me/liblzma/liblzma.tar.gz"
RUN wget "https://dl.zhamao.me/curl/curl-${VER_CURL}.tar.gz" -O curl.tar.gz

RUN tar -xf php.tar.gz && \
    tar -xf swoole.tar.gz && \
    tar -xf hash.tar.gz && \
    tar -xf inotify.tar.gz && \
    tar -xf redis.tar.gz && \
    tar -xf libxml2.tar.gz && \
    tar -xf liblzma.tar.gz && \
    tar -xf curl.tar.gz

RUN cd liblzma && \
    ./configure && \
    make -j4 && \
    make install && \
    echo "编译 liblzma 完成！"

RUN cd "libxml2-${VER_LIBXML2}" && \
    ./configure --with-lzma --without-python && \
    make -j4 && \
    make install && \
    echo "编译 libxml2 完成！"

RUN cd "curl-${VER_CURL}" && \
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
    echo "编译 curl 完成！"

RUN mv "swoole-${VER_SWOOLE}" "php-${VER_PHP}/ext/swoole" && \
    mv "redis-${VER_REDIS}" "php-${VER_PHP}/ext/redis" && \
    mv "inotify-${VER_INOTIFY}" "php-${VER_PHP}/ext/inotify"

COPY merge-curl-config.sh /tmp/

RUN chmod +x /tmp/php-arg.sh && \
    chmod +x /tmp/merge-curl-config.sh

RUN "php-${VER_PHP}/ext/swoole/clear.sh" && \
    cd "php-${VER_PHP}" && \
    "/tmp/merge-curl-config.sh" && \
    mv "/app/aa" "ext/curl/config.m4" && \
    ./buildconf --force

RUN cd "php-${VER_PHP}" && \
    chmod +x /tmp/php-arg.sh && \
    echo "Using "$(/tmp/php-arg.sh ${VER_PHP}) && sleep 5s && \
    ./configure LDFLAGS=-static $(/tmp/php-arg.sh ${VER_PHP})

RUN cd "php-${VER_PHP}" && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o \$(SAPI_CLI_PATH)/-all-static -o \$(SAPI_CLI_PATH)/g' "Makefile" && \
    sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "ext/swoole/include/swoole.h" && \
    make LDFLAGS=-ldl -j4 && \
    make install
        
