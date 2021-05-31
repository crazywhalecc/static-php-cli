#!/bin/sh

_main_dir="$(pwd)/"

_build_dir="$_main_dir/build"

mkdir "$_build_dir" > /dev/null 2>&1

for loop in "7.2.34" "7.3.28" "7.4.19" "8.0.6"
do
	sed -i 's/phpver"|"php") echo ".*/phpver"|"php") echo "'$loop'" ;;/g' "$_main_dir""static-compile-php.sh" && \
        rm -rf "$_main_dir""source/php.tar.gz" "$_main_dir""source/php-*" "$_main_dir""php-dist" && \
        "$_main_dir""static-compile-php.sh" && \
        cp "$_main_dir""php-dist/bin/php" "$_build_dir/" && \
        cd "$_build_dir/" && \
        tar -zcvf "php-$loop-static-bin-"$(uname -m)".tar.gz" "./php" && \
        mv "./php" "./php-$loop" && \
        cd "$_main_dir"
    if [ $? -ne 0 ]; then
        echo "Compile static php-$loop failed!"
        exit 1
    fi
done
