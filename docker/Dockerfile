FROM alpine:3.16

# define script basic information
# Version of this Dockerfile
ENV SCRIPT_VERSION=1.5.1
# Download address uses backup address 

ARG USE_BACKUP_ADDRESS

# (if downloading slowly, consider set it to yes)
ENV USE_BACKUP="${USE_BACKUP_ADDRESS}"


# APK repositories mirror address, if u r not in China, consider set USE_BACKUP=yes to boost
ENV LINK_APK_REPO='mirrors.ustc.edu.cn'
ENV LINK_APK_REPO_BAK='dl-cdn.alpinelinux.org'

RUN if [ "${USE_BACKUP}" = "" ]; then \
        export USE_BACKUP="no" ; \
    fi

RUN sed -i.backup 's/dl-cdn.alpinelinux.org/'${LINK_APK_REPO}'/g' /etc/apk/repositories ;

RUN if [ "${USE_BACKUP}" = "no" ]; then cp -f /etc/apk/repositories.backup /etc/apk/repositories; fi
RUN cat /etc/apk/repositories
# build requirements
RUN apk add bash file wget cmake gcc g++ jq autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses dialog > /dev/null
# php zlib dependencies
RUN apk add zlib-dev zlib-static > /dev/null
# php mbstring dependencies
RUN apk add oniguruma-dev > /dev/null
# php openssl dependencies
RUN apk add openssl-libs-static openssl-dev openssl > /dev/null
# php gd dependencies
RUN apk add libpng-dev libpng-static > /dev/null
# curl c-ares dependencies
RUN apk add c-ares-static c-ares-dev > /dev/null
# php event dependencies
RUN apk add libevent libevent-dev libevent-static > /dev/null
# php sqlite3 dependencies
RUN apk add sqlite sqlite-dev sqlite-libs sqlite-static > /dev/null
# php libzip dependencies
RUN apk add bzip2-dev bzip2-static bzip2 > /dev/null
# php micro ffi dependencies
RUN apk add libffi libffi-dev > /dev/null
# php gd event parent dependencies
RUN apk add zstd-static > /dev/null
# php readline dependencies
RUN apk add readline-static ncurses-static readline-dev > /dev/null
RUN apk add aria2

RUN mkdir /app

WORKDIR /app

ADD  ./ /app/

# RUN chmod +x /app/*.sh

# use proxy
# ENV http_proxy=http://192.168.3.26:8015
# ENV https_proxy=http://192.168.3.26:8015

# 提前下载好，就可以跳过两步
# (容器外提前执行 下面两个命令）
RUN sh ./download-library-batch-aria2.sh
RUN sh ./download-extension-batch-aria2.sh

#ENV http_proxy=''
#ENV https_proxy=''
RUN ls -lh source/libraries
RUN ls -lh source/extensions
# quick test complie
# RUN bash ./compile-deps.sh

RUN sh ./download.sh swoole ${USE_BACKUP} && \
    sh ./download.sh inotify ${USE_BACKUP} && \
    sh ./download.sh mongodb ${USE_BACKUP} && \
    sh ./download.sh event ${USE_BACKUP} && \
    sh ./download.sh redis ${USE_BACKUP} && \
    sh ./download.sh libxml2 ${USE_BACKUP} && \
    sh ./download.sh xz ${USE_BACKUP} && \
    sh ./download.sh curl ${USE_BACKUP} && \
    sh ./download.sh libzip ${USE_BACKUP} && \
    sh ./download.sh libiconv ${USE_BACKUP} && \
    sh ./download-git.sh dixyes/phpmicro phpmicro ${USE_BACKUP}

RUN bash ./compile-deps.sh
RUN echo -e "#!/usr/bin/env bash\n/app/compile-php.sh \$@" > /bin/build-php && chmod +x /bin/build-php
