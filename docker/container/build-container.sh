#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__ROOT__=$(cd ${__DIR__}/../;pwd)
cd ${__ROOT__}

#cd ${__DIR__}

export DOCKER_BUILDKIT=0
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=yes
