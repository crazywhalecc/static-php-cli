FROM alpine:latest

# define script basic information
# Version of this Dockerfile
ENV SCRIPT_VERSION=1.3.3
# Download address uses backup address 
# (if downloading slowly, consider set it to yes)
ENV USE_BACKUP=no

# Version of some manually-installed package
ENV VER_PHP=7.4.21

# APK repositories mirror address, if u r not in China, consider set USE_BACKUP=yes to boost
ENV LINK_APK_REPO='mirrors.ustc.edu.cn'
ENV LINK_APK_REPO_BAK='dl-cdn.alpinelinux.org'

RUN if [ "${USE_BACKUP}" = "yes" ]; then \
        echo "Using backup address..." && sleep 3s; \
    else \
        echo "Using original address..." && sleep 3s; \
    fi

RUN sed -i 's/dl-cdn.alpinelinux.org/'${LINK_APK_REPO}'/g' /etc/apk/repositories

# build requirements
RUN apk add bash wget cmake gcc g++ jq autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses
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
# php event dependencies
RUN apk add libevent libevent-dev libevent-static
# php sqlite3 dependencies
RUN apk add sqlite sqlite-dev sqlite-libs sqlite-static

RUN mkdir /app

WORKDIR /app

COPY ac_override_1 /app/
COPY ac_override_2 /app/
COPY download.sh /app/
COPY config.json /app/

RUN chmod +x /app/download.sh

RUN ./download.sh swoole ${USE_BACKUP} && \
    ./download.sh inotify ${USE_BACKUP} && \
    ./download.sh mongodb ${USE_BACKUP} && \
    ./download.sh event ${USE_BACKUP} && \
    ./download.sh redis ${USE_BACKUP} && \
    ./download.sh libxml2 ${USE_BACKUP} && \
    ./download.sh liblzma ${USE_BACKUP} && \
    ./download.sh curl ${USE_BACKUP}

RUN ./download.sh php ${USE_BACKUP} ${VER_PHP}

COPY extensions.txt /app/
COPY check-extensions.sh /app/
COPY compile-php.sh /app/

RUN chmod +x ./check-extensions.sh && \
    chmod +x ./compile-php.sh

RUN ./check-extensions.sh check_before_configure

RUN ./compile-php.sh ${VER_PHP}
