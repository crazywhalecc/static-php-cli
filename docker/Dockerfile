from alpine:latest

# define script basic information
# Version of this Dockerfile
ENV SCRIPT_VERSION=1.3.0
# Download address uses backup address 
# (if downloading slowly, consider set it to yes)
ENV USE_BACKUP=no

# Version of some manually-installed package
ENV VER_PHP=7.4.19
ENV VER_SWOOLE=4.6.7
ENV VER_HASH=1.5
ENV VER_INOTIFY=3.0.0
ENV VER_REDIS=5.3.4
ENV VER_LIBXML2=2.9.12
ENV VER_CURL=7.77.0
ENV VER_LIBLZMA=master

# LINK_OF_ADDRESS_ORIGIN
ENV LINK_APK_REPO='mirrors.ustc.edu.cn'
ENV LINK_APK_REPO_BAK='dl-cdn.alpinelinux.org'

RUN if [ "${USE_BACKUP}" = "yes" ]; then \
        echo "Using backup address..." && sleep 3s; \
    else \
        echo "Using original address..." && sleep 3s; \
    fi

RUN sed -i 's/dl-cdn.alpinelinux.org/'${LINK_APK_REPO}'/g' /etc/apk/repositories

# build requirements
RUN apk add cmake gcc g++ jq autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses
# php zlib dependencies
RUN apk add zlib-dev zlib-static
# php mbstring dependencies
RUN apk add oniguruma-dev
# php openssl dependencies
RUN apk add openssl-libs-static openssl-dev openssl
# php gd dependencies
RUN apk add libpng-dev libpng-static
# curl c-ares dependencies
RUN apk add c-ares-static c-ares-dev

RUN mkdir /app

WORKDIR /app

COPY download.sh /app/
COPY compile-php.sh /app/

RUN chmod +x /app/download.sh && \
    chmod +x /app/compile-php.sh

RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}
RUN ./download.sh hash ${USE_BACKUP} ${VER_HASH}
RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}
RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}
RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}
RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}
RUN ./download.sh swoole ${USE_BACKUP} ${VER_SWOOLE}

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
    echo "liblzma compiled!"

RUN cd "libxml2-${VER_LIBXML2}" && \
    ./configure --with-lzma --without-python && \
    make -j4 && \
    make install && \
    echo "libxml2 compiled!"

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
    echo "curl compiled!"

COPY ac_override_1 /
COPY ac_override_2 /
COPY compile-php.sh /app/

RUN apk add bash

# copy 
RUN mv "swoole-${VER_SWOOLE}" "php-${VER_PHP}/ext/swoole" && \
    mv "redis-${VER_REDIS}" "php-${VER_PHP}/ext/redis" && \
    mv "inotify-${VER_INOTIFY}" "php-${VER_PHP}/ext/inotify"

RUN cat /ac_override_1 /app/php-${VER_PHP}/ext/curl/config.m4 /ac_override_2 > /app/aa
RUN mv /app/aa /app/php-${VER_PHP}/ext/curl/config.m4

RUN cd "php-${VER_PHP}/ext/swoole/" && \
    ./clear.sh && \
    cd ../../ && \
    ./buildconf --force

RUN ./compile-php.sh ${VER_PHP}

RUN cd "php-${VER_PHP}" && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o \$(SAPI_CLI_PATH)/-all-static -o \$(SAPI_CLI_PATH)/g' "Makefile" && \
    sed -ie 's/swoole_clock_gettime(CLOCK_REALTIME/clock_gettime(CLOCK_REALTIME/g' "ext/swoole/include/swoole.h" && \
    make LDFLAGS=-ldl -j4 && \
    make install && \
    strip 
        
