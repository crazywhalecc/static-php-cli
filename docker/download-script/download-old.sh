#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

__ROOT__=$(cd ${__DIR__}/../;pwd)
cd ${__ROOT__}

USE_BACKUP=no

sh ./download.sh swoole ${USE_BACKUP} && \
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