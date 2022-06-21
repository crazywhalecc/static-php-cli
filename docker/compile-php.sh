#!/usr/bin/env bash

self_dir=$(cd "$(dirname "$0")";pwd)

# 通过 extensions.txt 生成一个 dialog 命令
function generate_ext_dialog_cmd() {
    list=$(cat "$self_dir/extensions.txt" | grep -v "^#" | grep -v "^$")
    echo -n "dialog --backtitle \"static-php-cli Compile Options\" --checklist \"Please select the extension you don't want to compile.\n\nNOTE: Use <space> to select or deselect items\n\n** Default is compiling all **\" 24 60 20 " > $self_dir/.ask_cmd.sh
    for loop in $list
    do
        case $loop in
        ^*)
            loop=$(echo ${loop:1} | xargs)
            echo -n "$loop '$loop Extension' off " >> $self_dir/.ask_cmd.sh 
	    ;;
        *) echo -n "$loop '$loop Extension' on " >> $self_dir/.ask_cmd.sh ;;
        esac
    done
    echo "2>$self_dir/extensions_install.txt" >> $self_dir/.ask_cmd.sh
}

# PHP 编译参数生成
function php_compile_args() {
    _php_arg="--prefix=$self_dir/php-dist"
    _php_arg="$_php_arg --disable-all"
    _php_arg="$_php_arg --enable-shared=no"
    _php_arg="$_php_arg --enable-static=yes"
    _php_arg="$_php_arg --enable-inline-optimization"
    _php_arg="$_php_arg --with-layout=GNU"
    _php_arg="$_php_arg --with-pear=no"
    _php_arg="$_php_arg --disable-cgi"
    _php_arg="$_php_arg --disable-phpdbg"
    _php_arg="$_php_arg $($self_dir/check-extensions.sh check_in_configure $1)"
    echo $_php_arg
}

# 第一个参数用于使用镜像地址还是原地址（mirror为镜像地址，original为原地址）
if [ "$1" = "" ]; then
    dialog --backtitle "static-php-cli Compile Options" --yesno "<Yes>: Use mirror download address, mainland China users recommended.\n\n<No>: Use original address, global users recommended." 10 50
    test $? == 0 && USE_BACKUP="no" || USE_BACKUP="yes"
else
    test "$1" != "mirror" && USE_BACKUP="yes" || USE_BACKUP="no"
fi

# 第二个参数用于规定编译的 PHP 版本
if [ "$2" = "" ]; then
    dialog --backtitle "static-php-cli Compile Options" --inputbox "Please input your PHP version to compile" 10 50 "8.1.7" 2>$self_dir/.phpver
    if [ $? != 0 ]; then
        clear
        echo "canceled Compiling PHP." && rm -f $self_dir/.phpver
        exit 1
    else
        VER_PHP=$(cat $self_dir/.phpver)
        rm -f $self_dir/.phpver
    fi
else
    VER_PHP=$2
fi

# 第三个参数用于是否直接安装，如果留空则询问编译的扩展，如果填入 all，则直接编译所有的扩展
if [ "$3" != "all" ]; then
    generate_ext_dialog_cmd && cat $self_dir/.ask_cmd.sh && chmod +x $self_dir/.ask_cmd.sh && $self_dir/.ask_cmd.sh
    if [ $? != 0 ]; then
        clear
        echo "canceled Compiling PHP while selecting extensions." && rm -rf $self_dir/.ask_cmd.sh
        exit 1
    fi
    rm -f $self_dir/.ask_cmd.sh
else
    cp $self_dir/extensions.txt $self_dir/extensions_install.txt
fi

# 第四个参数用于输出 PHP 和 micro 二进制文件的位置
if [ "$4" = "" ]; then
    dialog --backtitle "static-php-cli Compile Options" --inputbox "Please input compiled output directory" 10 50 "/dist/" 2>$self_dir/.outdir
    if [ $? != 0 ]; then
        clear
        echo "canceled setting output dir, compiling PHP stopped." && rm -f $self_dir/.outdir
        exit 1
    else
        OUT_DIR=$(cat $self_dir/.outdir)
        rm -f $self_dir/.outdir
    fi
else
    OUT_DIR=$4
fi

if [ ! -d "$OUT_DIR" ]; then
    mkdir -p "$OUT_DIR"
fi

# 下载 PHP


echo "All done. Downloading PHP ..."
if [ -d "$self_dir/source/php-$VER_PHP" ]; then
    rm -rf "$self_dir/source/php-$VER_PHP"
fi
$self_dir/download.sh php ${USE_BACKUP} ${VER_PHP} || { echo "Download PHP failed!" && exit 1 ; }
# 选择性编译依赖的库、移动需要安装的扩展到 PHP 目录
$self_dir/check-extensions.sh check_before_configure ${VER_PHP} || { echo "Install required library failed!" && exit 1 ; }
# 编译 PHP
echo "Compiling PHP ..."
php_dir=$(find $self_dir/source -name "php-$VER_PHP" -type d | tail -n1)
cd $php_dir && \
    ./buildconf --force && \
    ./configure LDFLAGS=-static $(php_compile_args $VER_PHP) && \
    $self_dir/check-extensions.sh check_after_configure ${VER_PHP} && \
    sed -ie 's/-export-dynamic//g' "Makefile" && \
    sed -ie 's/-o $(SAPI_CLI_PATH)/-all-static -o $(SAPI_CLI_PATH)/g' "Makefile" && \
    #sed -ie 's/$(PHP_GLOBAL_OBJS) $(PHP_BINARY_OBJS) $(PHP_MICRO_OBJS)/$(PHP_GLOBAL_OBJS:.lo=.o) $(PHP_BINARY_OBJS:.lo=.o) $(PHP_MICRO_OBJS:.lo=.o)/g' "Makefile" && \
    make LDFLAGS="-ldl" -j$(cat /proc/cpuinfo | grep processor | wc -l) && \
    make install-cli && \
    $self_dir/check-extensions.sh finish_compile && \
    strip $self_dir/php-dist/bin/php && \
    echo "Copying php binary to $OUT_DIR ..." && \
    cp $self_dir/php-dist/bin/php $OUT_DIR/ && \
    test -f $php_dir/sapi/micro/micro.sfx && \
    echo "Copying micro.sfx binary to $OUT_DIR ..." && \
    cp $php_dir/sapi/micro/micro.sfx $OUT_DIR/ || { exit 0 ; }
