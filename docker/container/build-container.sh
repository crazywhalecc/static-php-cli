#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

cd ${__DIR__}

export DOCKER_BUILDKIT=0
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=yes
