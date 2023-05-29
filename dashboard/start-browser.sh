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

OS=$(uname -s)
ARCH=$(uname -m)

if [ $OS = 'Linux' ]; then
  {

   xdg-open "http://0.0.0.0:9502"

   # gnome-terminal --window -- xdg-open 'http://0.0.0.0:9502'
   # gnome-terminal --window -- nautilus ${__PROJECT__}
   # gnome-screenshot --help
   # gnome-screenshot -i
  }
elif [ $OS = ''Darwin'' ]; then
    open -a "Google Chrome"  "http://0.0.0.0:9502"
fi
