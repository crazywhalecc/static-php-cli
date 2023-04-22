#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__PROJECT__=$(
  cd ${__DIR__}/../../../
  pwd
)
cd ${__PROJECT__}
mkdir -p var
cd ${__PROJECT__}/var

test -f swoole-cli-v5.0.2-linux-x64.tar.xz || wget -O swoole-cli-v5.0.2-linux-x64.tar.xz  https://github.com/swoole/swoole-src/releases/download/v5.0.2/swoole-cli-v5.0.2-linux-x64.tar.xz
test -f swoole-cli-v5.0.2-linux-x64.tar ||  xz -d -k swoole-cli-v5.0.2-linux-x64.tar.xz
test -f swoole-cli ||  tar -xvf swoole-cli-v5.0.2-linux-x64.tar
chmod a+x swoole-cli

test -f composer.phar ||  wget -O composer.phar https://getcomposer.org/download/2.5.5/composer.phar
chmod a+x composer.phar

cp -f swoole-cli /usr/local/bin/
cp -f composer.phar /usr/local/bin/

ln -sf /usr/local/bin//swoole-cli /usr/local/bin//php
ln -sf /usr/local/bin//composer.phar /usr/local/bin//composer

