#!/bin/sh

_use_backup="$2"

function download_file() {
  downloader="wget"
  type wget >/dev/null 2>&1 || { downloader="curl"; }
  if [ "$downloader" = "wget" ]; then
    _down_prefix="O"
  else
    _down_prefix="o"
  fi
  _down_symbol=0
  if [ ! -f "$2" ]; then
    $downloader "$1" -$_down_prefix "$2" >/dev/null 2>&1 && \
    echo "$1 完成！ $2" && _down_symbol=1
  else
    echo "$2 已存在！" && _down_symbol=1
  fi
  if [ $_down_symbol == 0 ]; then
    echo "下载 $1 失败！请检查网络连接！"
    rm -rf "$2"
    return 1
  fi
  return 0
}

function lib_download_link() {
    if [ "$_use_backup" = "yes" ]; then
        case $1 in
        "php")          echo "https://www.php.net/distributions/php-$2.tar.gz" ;;
        "swoole")       echo "https://pecl.php.net/get/swoole-$2.tgz" ;;
        "hash")         echo "https://pecl.php.net/get/hash-$2.tgz" ;;
        "inotify")      echo "https://pecl.php.net/get/inotify-$2.tgz" ;;
        "redis")        echo "https://pecl.php.net/get/redis-$2.tgz" ;;
        "libxml2")      echo "http://xmlsoft.org/sources/libxml2-$2.tar.gz" ;;
        "liblzma")      echo "https://github.com/kobolabs/liblzma/archive/refs/heads/master.zip" ;;
        "curl")         echo "https://curl.haxx.se/download/curl-$2.tar.gz" ;;
        *)              echo "unknown" ;;
        esac
    else 
        case $1 in
        "php")          echo "http://mirrors.zhamao.xin/php/php-$2.tar.gz" ;;
        "swoole")       echo "http://mirrors.zhamao.xin/pecl/swoole-$2.tgz" ;;
        "hash")         echo "http://mirrors.zhamao.xin/pecl/hash-$2.tgz" ;;
        "inotify")      echo "http://mirrors.zhamao.xin/pecl/inotify-$2.tgz" ;;
        "redis")        echo "http://mirrors.zhamao.xin/pecl/redis-$2.tgz" ;;
        "libxml2")      echo "https://dl.zhamao.me/libxml2/libxml2-$2.tar.gz" ;;
        "liblzma")      echo "https://dl.zhamao.me/liblzma/liblzma.tar.gz" ;;
        "curl")         echo "https://dl.zhamao.me/curl/curl-$2.tar.gz" ;;
        *)              echo "unknown" ;;
        esac
    fi
}

function lib_x_cmd() {
    case $1 in
        "php"|"swoole"|"hash"|"inotify"|"redis"|"libxml2"|"curl")
            _x_cmd="tar"
            ;;
        "liblzma") if [ "$_use_backup" = "yes" ]; then _x_cmd="unzip"; else _x_cmd="tar"; fi ;;
        *) _x_cmd="unknown" ;;
    esac
    case $2 in
    "cmd")
        echo $_x_cmd
        ;;
    "file-prefix")
        case $_x_cmd in
        "tar") echo "-zxvf" ;;
        "unzip") echo "" ;;
        esac
        ;;
    "out-prefix")
        case $_x_cmd in
        "tar") echo "-C" ;;
        "unzip") echo "-d" ;;
        esac
        ;;
    esac
}

function lib_x() {
    $(lib_x_cmd $1 cmd) $(lib_x_cmd $1 file-prefix) "$_source_dir/$(lib_x_dirname $1 file)" $(lib_x_cmd $1 out-prefix) "$_source_dir/"
}

# 获取解压后的源码根目录
function lib_x_dirname() {
    case $1 in
        "php"|"swoole"|"hash"|"inotify"|"redis"|"libxml2"|"curl")
            if [ "$2" = "file" ]; then _name_prefix=".tar.gz"; else _name_prefix=""; fi
            echo "$1-$(lib_ver $1)$_name_prefix"
            ;;
        "liblzma")
            if [ "$_use_backup" = "yes" ]; then 
                if [ "$2" = "file" ]; then _name_prefix=".zip"; else _name_prefix=""; fi
                echo "$1-$(lib_ver $1)$_name_prefix"
            else
                if [ "$2" = "file" ]; then _name_prefix=".tar.gz"; else _name_prefix=""; fi
                echo "$1""$_name_prefix"
            fi
            ;;
        *)
            echo "unknown" 
            ;;
    esac
}

download_file $(lib_download_link $1 $3) $(lib_x_firname $1 file)