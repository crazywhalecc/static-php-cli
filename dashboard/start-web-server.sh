#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__PROJECT__=$(
  cd ${__DIR__}/../
  pwd
)
cd ${__DIR__}

php -S 0.0.0.0:9502 -t ${__DIR__}/public/