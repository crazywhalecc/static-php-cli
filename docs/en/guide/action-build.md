# GitHub Action Build

Action Build refers to compiling directly using GitHub Action.

If you don't want to compile it yourself, you can download the artifact from the existing Action in this project, 
or you can download it from a self-hosted server：[Enter](https://dl.static-php.dev/static-php-cli/common/).

> Self-hosted binaries are also built from Actions: [repo](https://github.com/static-php/static-php-cli-hosted).
> The extensions included are: bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,
> pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip

## Build Guide

Using GitHub Action makes it easy to build a statically compiled PHP and phpmicro, 
while also defining the extensions to compile.

1. Fork project.
2. Go to the Actions of the project and select `CI`.
3. Select `Run workflow`, fill in the PHP version you want to compile, the target type, and the list of extensions. (extensions comma separated, e.g. `bcmath,curl,mbstring`)
4. After waiting for about a period of time, enter the corresponding task and get `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

> If you need to build in other environments, you can use [manual build](./manual-build).

## Extensions

You can go to [extensions](./extensions) check here to see if all the extensions you need currently support.
and then go to [command generator](./cli-generator) select the extension you need to compile, copy the extensions string to `extensions` option.
