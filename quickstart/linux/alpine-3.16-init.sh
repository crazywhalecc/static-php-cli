#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
cd ${__DIR__}


# use china mirror
# bash quickstart/linux/x86_64/alpine-3.16-init.sh --mirror china
mirror=''
while [ $# -gt 0 ]; do
	case "$1" in
		--mirror)
			mirror="$2"
			shift
			;;
		--*)
			echo "Illegal option $1"
			;;
	esac
	shift $(( $# > 0 ? 1 : 0 ))
done

case "$mirror" in
	china)
		test -f /etc/apk/repositories.save || cp /etc/apk/repositories /etc/apk/repositories.save
    sed -i 's/dl-cdn.alpinelinux.org/mirrors.tuna.tsinghua.edu.cn/g' /etc/apk/repositories
		;;

esac


apk update

apk add vim alpine-sdk xz autoconf automake linux-headers clang-dev clang lld libtool cmake bison re2c gettext coreutils
apk add bash p7zip zip unzip flex  pkgconf ca-certificates
apk add wget git curl