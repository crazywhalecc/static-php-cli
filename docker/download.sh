#!/bin/sh

_use_backup="$2"

SELF_DIR=$(cd "$(dirname "$0")";pwd)

if [ ! -d "source" ]; then
    mkdir source
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

if [ "$3" != "" ]; then
    wget -q --show-progress "$(readconf ".$1.link$_use_backup" | sed 's/{version}/'$3'/g')"
else
    wget -q --show-progress "$(readconf ".$1.link$_use_backup" | sed 's/{version}/'$(readconf ".$1.version")'/g')"
fi

if [ $? == 0 ]; then
    archive_file_tar=$(find . -name "$1*.*" | grep -E ".tar|.gz|.tgz" | tail -n1)
    archive_file_zip=$(find . -name "$1*.*" | grep -E ".zip" | tail -n1)
    if [ "$archive_file_tar" != "" ]; then
        tar -zxvf $archive_file_tar && rm $archive_file_tar
    elif [ "$archive_file_zip" != "" ]; then
        unzip $archive_file_zip && rm $archive_file_zip
    else
        echo "Unable to find downloaded file, only support '.tar.gz', '.tgz', '.zip' file!"
        exit 1
    fi
else
    echo "Download failed! "
    exit 1
fi