#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)

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

sed -i "s@deb.debian.org@mirrors.ustc.edu.cn@g" /etc/apt/sources.list && \
sed -i "s@security.debian.org@mirrors.ustc.edu.cn@g" /etc/apt/sources.list

apt update -y
apt install -y   git curl wget ca-certificates
apt install -y   xz-utils autoconf automake  libclang-13-dev clang lld libtool cmake bison re2c gettext  coreutils lzip zip unzip
apt install -y   pkg-config bzip2 flex


# apt install build-essential linux-headers-$(uname -r)