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
cd ${__PROJECT__}


mkdir -p ${__DIR__}/public/data
cp -f ${__PROJECT__}/config/ext.json ${__DIR__}/public/data
