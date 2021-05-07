# static-php-swoole
Compile A Statically Linked PHP With Swoole and other Extensions. [English README](/README-en.md)

BTW, It's only for CLI mode.

[![version](https://img.shields.io/badge/version-1.1.1-green.svg)]()

## Requirements
- Tested on `x86_64` and `aarch64` platform, others have not tested.
- Requiring Alpine Linux (version >= 3.13), or requiring musl-libc
- Support WSL2
- Supporting PHP version >= 7.3

## Start
You can directly download static binary in Release.

Here's help command to compile it yourself:
```bash
# Compile script
./static-compile-php.sh
# And now you get `php-dist/bin/php` file!
```

## Library version
- php: 7.4.18
- libxml2: 2.9.10
- curl: 7.76.1

## Including PHP extensions
- bcmath
- calendar
- ctype
- filter
- openssl
- pcntl
- iconv
- inotify (3.0.0)
- json
- mbstring
- phar
- curl
- pdo
- gd
- pdo_mysql
- mysqlnd
- sockets
- swoole (4.6.6)
- redis (5.3.4)
- simplexml
- dom
- xml
- xmlwriter
- xmlreader
- posix
- tokenizer

## Running preview
After-compile status
![image](https://user-images.githubusercontent.com/20330940/116291663-6df47580-a7c7-11eb-8df3-6340c6f87055.png)

Run Swoft framework directly
![image](https://user-images.githubusercontent.com/20330940/116053161-f16d7400-a6ac-11eb-87b8-e510c6454861.png)

## Todo List
- [X] curl/libcurl extension support
- [ ] Alternative extension compiling
- [ ] php.ini support
- [ ] Make composer together
- [ ] i18n

## References
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
