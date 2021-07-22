#!/bin/sh

_use_backup="$2"

SELF_DIR=$(cd "$(dirname "$0")";pwd)

if [ ! -d "source" ]; then
    mkdir source
fi

if [ ! -d "source/cache" ]; then
    mkdir source/cache
fi

function readconf() {
    cat $SELF_DIR/config.json | jq $@ | sed 's/\"//g'
}

cd source

if [ "$_use_backup" = "yes" ]; then
    _use_backup="_2"
else
    _use_backup=""
fi

archive_find_tar=$(find cache/ -name "$1.*" | grep -E ".tgz" | tail -n1)
archive_find_zip=$(find cache/ -name "$1.*" | grep -E ".zip" | tail -n1)

if [ "$archive_find_tar" != "" ]; then
    echo "Using cache for $1 ($archive_find_tar)"
    tar -zxvf "$archive_find_tar"
elif [ "$archive_find_zip" != "" ]; then
    echo "Using cache for $1 ($archive_find_zip)"
    unzip $archive_find_zip -d "$SELF_DIR/source"
else
    if [ "$3" != "" ]; then
        wget -q --show-progress "$(readconf ".$1.link$_use_backup" | sed 's/{version}/'$3'/g')"
    else
        echo "Downloading"
        wget -q --show-progress "$(readconf ".$1.link$_use_backup" | sed 's/{version}/'$(readconf ".$1.version")'/g')"
    fi

    if [ $? == 0 ]; then
        archive_file_tar=$(find . -name "$1*.*" | grep -E ".tar|.gz|.tgz" | tail -n1)
        archive_file_zip=$(find . -name "*.zip" | tail -n1)
        if [ "$archive_file_tar" != "" ]; then
            tar -zxvf $archive_file_tar && mv $archive_file_tar $SELF_DIR/source/cache/$1.tgz
        elif [ "$archive_file_zip" != "" ]; then
            unzip $archive_file_zip && mv $archive_file_zip $SELF_DIR/source/cache/$1.zip
        else
            echo "Unable to find downloaded file, only support '.tar.gz', '.tgz', '.zip' file!"
            exit 1
        fi
    else
        echo "Download $1 failed! (at $?)"
        exit 1
    fi
fi