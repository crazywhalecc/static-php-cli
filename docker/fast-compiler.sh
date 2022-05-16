#!/bin/sh

# This script needs alpine linux system.

test "$VER_PHP" = "" && VER_PHP="8.1.6"
test "$USE_BACKUP" = "" && USE_BACKUP="no"

LINK_APK_REPO='mirrors.ustc.edu.cn'
LINK_APK_REPO_BAK='dl-cdn.alpinelinux.org'

if [ "${USE_BACKUP}" = "yes" ]; then \
    echo "Using backup address..." && sleep 1s
    LINK_APK_REPO=${LINK_APK_REPO_BAK}
else
    echo "Using original address..." && sleep 1s
fi

sed -i 's/dl-cdn.alpinelinux.org/'${LINK_APK_REPO}'/g' /etc/apk/repositories

# build requirements
apk add bash file wget cmake gcc g++ jq autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses
# php zlib dependencies
apk add zlib-dev zlib-static
# php mbstring dependencies
apk add oniguruma-dev
# php openssl dependencies
apk add openssl-libs-static openssl-dev openssl
# php gd dependencies
apk add libpng-dev libpng-static
# curl c-ares dependencies
apk add c-ares-static c-ares-dev
# php event dependencies
apk add libevent libevent-dev libevent-static
# php sqlite3 dependencies
apk add sqlite sqlite-dev sqlite-libs sqlite-static
# php libzip dependencies
apk add bzip2-dev bzip2-static bzip2
# php micro ffi dependencies
apk add libffi libffi-dev

chmod +x download.sh check-extensions.sh compile-php.sh compile-micro.sh

./download.sh swoole ${USE_BACKUP} && \
    ./download.sh inotify ${USE_BACKUP} && \
    ./download.sh mongodb ${USE_BACKUP} && \
    ./download.sh event ${USE_BACKUP} && \
    ./download.sh redis ${USE_BACKUP} && \
    ./download.sh libxml2 ${USE_BACKUP} && \
    ./download.sh xz ${USE_BACKUP} && \
    ./download.sh curl ${USE_BACKUP} && \
    ./download.sh libzip ${USE_BACKUP} && \
    ./download.sh php ${USE_BACKUP} ${VER_PHP} && \
    ./check-extensions.sh check_before_configure && \
    ./compile-micro.sh ${VER_PHP}

