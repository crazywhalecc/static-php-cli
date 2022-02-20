# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. 

BTW, It's only for CLI mode.

[![version](https://img.shields.io/badge/version-1.3.3-green.svg)]()
![Build Actions](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build-php.yml/badge.svg)

## Compilation Requirements
- Tested on `x86_64` and `aarch64` platform, others have not tested.
- Docker required (or alpine linux 3.12+)
- Supporting PHP version from 7.2 to 8.1

## Running Requirements
Linux

## Start
You can directly download static binary from this link.

<https://dl.zhamao.me/php-bin/file/>

Here's help command to compile it yourself:
```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli/docker
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=yes --build-arg COMPILE_PHP_VERSION=7.4.23
```

After compilation you can use command to get static php binary file:
```bash
mkdir dist
docker run --rm -v $(pwd)/dist:/dist/ -it static-php cp php-dist/bin/php /dist/
cd dist
file ./php
```

To customize PHP extensions, edit `docker/extensions.txt` file, and rules below:
- Use `#` as comment, to mark not install
- extensions name uses lower case, and default file contains all supported extensions, if u need other extensions, consider write an Issue

## Supported PHP extensions
| Support | PHP Ext Name | Version | Comments                                 |
| ------- | ------------ | ------- | ---------------------------------------- |
| yes     | bcmath       | *       |                                          |
| yes     | calendar     | *       |                                          |
| yes     | ctype        | *       |                                          |
| yes     | curl         | *       |                                          |
| yes     | dom          | *       |                                          |
|         | event        |         |                                          |
| yes     | filter       | *       |                                          |
| yes     | gd           | *       |                                          |
| yes     | hash         | *       |                                          |
| yes     | iconv        | *       |                                          |
| yes     | inotify      | 3.0.0   |                                          |
| yes     | json         | *       |                                          |
| yes     | libxml       | *       |                                          |
| yes     | mbstring     | *       |                                          |
| yes     | mongodb      | >=1.9.1 | not tested                               |
|         | mysqli       |         |                                          |
| yes     | mysqlnd      | *       |                                          |
| yes     | openssl      | *       |                                          |
| yes     | pcntl        | *       |                                          |
| yes     | pdo          | *       |                                          |
| yes     | pdo_mysql    | *       |                                          |
|         | pdo_pgsql    | *       |                                          |
| yes     | phar         | *       |                                          |
| yes     | posix        | *       |                                          |
| yes     | redis        | *       |                                          |
| yes     | simplexml    | *       |                                          |
| yes     | sockets      | *       |                                          |
| yes     | sqlite3      | *       |                                          |
| yes     | swoole       | >=4.6.6 | support mysqlnd, sockets, openssl, redis |
| yes     | tokenizer    | *       |                                          |
| yes     | xml          | *       |                                          |
| yes     | xmlreader    | *       |                                          |
| yes     | xmlwriter    | *       |                                          |
|         | zip          |         |                                          |
| yes     | zlib         | *       |                                          |

## Customization
- `docker/Dockerfile` edit `VER_PHP=x.x.x` to switch PHP version.
- `docker/Dockerfile` edit `USE_BACKUP=yes` to use backup download address (download faster if you are not in mainland China).
- `docker/extensions.txt` edit extensions.
- `docker/compile-php.sh` file `php_compile_args` function to adjust PHP configure arguments.
- `docker/check-extensions.sh` file `check_in_configure` function to adjust extensions' configure arguments.
- `docker/config.json` edit extensions and dependencies version and download links.

## Current Issue
- [ ] Not support event(libevent), because of its `config.m4` and code.
- [ ] Swoole not support `--enable-swoole-curl`.
- [ ] Not support readline, maybe caused by ncurses library.
- [X] Not support curl (solved)
- [X] Customize extensions to compile
- [ ] php.ini integration
- [X] i18n (including README and scripts)

## Running preview
After-compile status

![image](https://user-images.githubusercontent.com/20330940/120911319-219b3000-c6b9-11eb-82d1-b4309cfca8b3.png)

Run Workerman directly

![image](https://user-images.githubusercontent.com/20330940/120911301-f7e20900-c6b8-11eb-99eb-ebc84ab95df0.png)

## References
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
