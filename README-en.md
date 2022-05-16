# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. 

BTW, It's only for CLI mode.

[![version](https://img.shields.io/badge/version-1.5.0-green.svg)]()
![Build Actions](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build-php.yml/badge.svg)

## Compilation Requirements

- Supporting architecture: `x86_64`, `arm64(aarch64)`, `armv7(armv7l)` 
- Docker required (or alpine linux 3.13+)
- Supporting PHP version from 7.2 to 8.1

## Running Requirements

Linux

## Usage

1. Directly download static binary from this link.

<https://dl.zhamao.xin/php-bin/file/>

2. Use fast install script `install-runtime.sh` to download static php and composer distribution into `runtime/` directory

But this script has some Chinese comments and prompts, if you cannot understand or have to use it in English, I will make an pure international version! :)

```bash
bash -c "`curl -fsSL https://raw.githubusercontent.com/crazywhalecc/static-php-cli/master/install-runtime.sh`"
```

## Packing PHP Code into a Static Binary

From v1.5.0, we support packing PHP code into a static binary. You can pack your PHP code into a static binary by micro.

You can directly download `micro-` prefix file, untar it and you will get file `micro.sfx`.

Here's a simple example to use it:

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app

# If packing phar into a static binary, just change code.php to your phar path.
```

> Thanks <https://github.com/dixyes/phpmicro>

## Compiling

Here's help command to compile it yourself:

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli/docker
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=yes --build-arg COMPILE_PHP_VERSION=7.4.29
```

After compilation you can use command to get static php binary file:
```bash
mkdir dist
docker run --rm -v $(pwd)/dist:/dist/ -it static-php cp php-dist/bin/php /dist/
cd dist
file ./php
```

If you don't want to use docker, a single script for compiling:

```bash
cd docker
# Change PHP Version
export VER_PHP="8.1.6"
# Use Original download link (Default is China mainland mirror link, for others please use 'yes' for original link)
export USE_BACKUP="yes"
./fast-compiler.sh
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
| yes     | exif         | *       |                                          |
| yes     | filter       | *       |                                          |
| yes     | fileinfo     | *       |                                          |
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
| yes     | pdo_sqlite   | *       |                                          |
|         | pdo_pgsql    | *       |                                          |
| yes     | phar         | *       |                                          |
| yes     | posix        | *       |                                          |
| yes     | redis        | *       |                                          |
| yes     | shmop        | *       |                                          |
| yes     | simplexml    | *       |                                          |
| yes     | soap         | *       |                                          |
| yes     | sockets      | *       |                                          |
| yes     | sqlite3      | *       |                                          |
| yes     | swoole       | >=4.6.6 | support mysqlnd, sockets, openssl, redis |
| yes     | tokenizer    | *       |                                          |
| yes     | xml          | *       |                                          |
| yes     | xmlreader    | *       |                                          |
| yes     | xmlwriter    | *       |                                          |
| yes     | zip          | *       | not support `bzip2`, `lzma` compression  |
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

### Using static binary

<img width="881" alt="未命名" src="https://user-images.githubusercontent.com/20330940/168441751-e62cb8d4-a3c8-42d9-b34e-d804b39756a1.png">

### Using swoole application packed with micro



## References
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
