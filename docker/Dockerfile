FROM alpine:latest

# define script basic information
# Version of this Dockerfile
ENV SCRIPT_VERSION=1.4.0
# Download address uses backup address 

ARG USE_BACKUP_ADDRESS
ARG COMPILE_PHP_VERSION

# (if downloading slowly, consider set it to yes)
ENV USE_BACKUP="${USE_BACKUP_ADDRESS}"

# Version of some manually-installed package
ENV VER_PHP="${COMPILE_PHP_VERSION}"

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
RUN apk add bash wget cmake gcc g++ jq autoconf git libstdc++ linux-headers make m4 libgcc binutils ncurses > /dev/null
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

RUN mkdir /app

WORKDIR /app

COPY ac_override_1 /app/
COPY ac_override_2 /app/
COPY download.sh /app/
COPY config.json /app/

RUN chmod +x /app/download.sh

RUN echo "Downloading Extensions ..."
RUN ./download.sh swoole ${USE_BACKUP} > /dev/null && \
    ./download.sh inotify ${USE_BACKUP} > /dev/null && \
    ./download.sh mongodb ${USE_BACKUP} > /dev/null && \
    ./download.sh event ${USE_BACKUP} > /dev/null && \
    ./download.sh redis ${USE_BACKUP} > /dev/null && \
    ./download.sh libxml2 ${USE_BACKUP} > /dev/null && \
    ./download.sh liblzma ${USE_BACKUP} > /dev/null && \
    ./download.sh curl ${USE_BACKUP} > /dev/null 

RUN echo "Downloading PHP ${VER_PHP} ..."
RUN ./download.sh php ${USE_BACKUP} ${VER_PHP} > /dev/null

COPY extensions.txt /app/
COPY check-extensions.sh /app/
COPY compile-php.sh /app/

RUN chmod +x ./check-extensions.sh && \
    chmod +x ./compile-php.sh

RUN echo "Checking and Compiling Dependencies ..."
RUN ./check-extensions.sh check_before_configure > /dev/null

RUN echo "Compiling PHP ..."
RUN ./compile-php.sh ${VER_PHP} > /dev/null 2>&1
