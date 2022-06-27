# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. 

Compile A Single Binary With PHP Code.

[![version](https://img.shields.io/badge/version-1.5.2-green.svg)]()
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
> Note: It means that your PHP code won't be compiled and you can't protect your source code by using micro! 
>
> Special thanks: <https://github.com/dixyes/phpmicro>

## Compiling

Here's help command to compile it yourself:

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli/docker
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=yes
# Making a directory to put binary files
mkdir dist
# It will ask you for PHP version, extensions, and compile static binaries
docker run --rm -v $(pwd)/dist:/dist/ -it static-php build-php
```

After compilation you can use command to get static php binary file.

```bash
cd dist
file ./php
```

If you don't want to use docker, a single script for compiling in **Alpine Linux**:

```bash
cd docker
# Change PHP Version
export VER_PHP="8.1.7"
# Use Original download link (Default is China mainland mirror link, for others please use 'yes' for original link)
export USE_BACKUP="yes"
./fast-compiler.sh
```

To customize PHP extensions, edit `docker/extensions.txt` file, and rules below:
- Use `^` as deselect, to mark not install. Use `#` as comments.
- extensions name uses lower case, and default file contains all supported extensions, if u need other extensions, consider write an Issue

## Supported PHP extensions
| Support | PHP Ext Name | Version | Comments                                 |
| ------- | ------------ | ------- | ---------------------------------------- |
| yes     | bcmath       | *       |                                          |
| yes     | calendar     | *       |                                          |
| yes     | ctype        | *       |                                          |
| yes     | curl         | *       |                                          |
| yes     | dom          | *       |                                          |
| yes     | event        | >=3.0.8 | author's bitbucket version, not pecl     |
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
| yes, not compiled | protobuf        | *       | Not compiled and enabled as default |
| yes     | readline     | *       | Not support `./php -a`                   |
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
- If you are going to run without prompt, Just add it to the end of `docker run xxx` cmd according to the parameters given below.

> 1st parameter `original` represents that you are using global original download address to fetch dependencies, if you are in mainland China, use `mirror`.
> 
> 2nd parameter `8.1.7` is your PHP version you are compiling.
> 
> 3rd parameter `all` represents that you will compile all supported extensions.
> 
> 4th parameter `/dist/` is your binary output directory.
> 
> For example, `docker run --rm -v $(pwd)/dist:/dist/ -it static-php build-php original 8.1.7 all /dist/`

- `docker/extensions.txt` edit extensions.
- `docker/compile-php.sh` file `php_compile_args` function to adjust PHP configure arguments.
- `docker/check-extensions.sh` file `check_in_configure` function to adjust extensions' configure arguments.
- `docker/config.json` edit extensions and dependencies version and download links.

## Current Issue
- [X] Not support event(libevent), because of its `config.m4` and code.
- [ ] Swoole not support `--enable-swoole-curl`.
- [X] Not support readline, maybe caused by ncurses library.
- [X] Not support curl (solved)
- [X] Customize extensions to compile
- [X] php.ini integration
- [X] i18n (including README and scripts)

## Running preview

### Using static binary

<img width="881" alt="未命名" src="https://user-images.githubusercontent.com/20330940/168441751-e62cb8d4-a3c8-42d9-b34e-d804b39756a1.png">

### Using swoole application packed with micro

<img width="937" alt="all" src="https://user-images.githubusercontent.com/20330940/168557743-b8f92263-712f-490e-9fe0-831597741595.png">

## References
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
