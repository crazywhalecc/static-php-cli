#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__ROOT__=$(cd ${__DIR__}/../;pwd)
cd ${__DIR__}

# https://aria2.github.io/manual/en/html/aria2c.html#http-ftp-segmented-downloads
# https://aria2.github.io/manual/en/html/aria2c.html
# -with-config-file-path=/usr/local/php/etc
# -U, --user-agent
# aria2c -h
# aria2c --conf-path=/etc/aria2/aria2.conf

:<<EOF
-c, --continue [true|false]
-s, --split=<N>
-x, --max-connection-per-server=<NUM>
-k, --min-split-size=<SIZE>
-j, --max-concurrent-downloads=<N>
-i, --input-file=<FILE>
EOF

user_agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36'


test -f download_library_urls.txt  && aria2c -c -j 10 -s 10 -x 8 -k 10M --allow-overwrite=true --max-tries=30  --retry-wait=15 --user-agent=$user_agent \
 -d libraries --input-file=download_library_urls.txt

mkdir -p ${__ROOT__}/source/libraries
awk 'BEGIN { cmd="cp -ri libraries/* ${__ROOT__}/source/libraries/"  ; print "n" |cmd; }'

cd ${__DIR__}