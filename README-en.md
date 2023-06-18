# static-php-cli

Compile A Statically Linked PHP With Swoole and other Extensions.

Compile a purely static php-cli binary file with various extensions to make PHP applications more portable! (cli SAPI)

<img width="600" alt="截屏2023-05-02 15 53 13" src="https://user-images.githubusercontent.com/20330940/235610282-23e58d68-bd35-4092-8465-171cff2d5ba8.png">

You can also use the micro binary file to combine php binary and php source code into one for distribution!
This feature is provided by [dixyes/phpmicro](https://github.com/dixyes/phpmicro). (micro SAPI)

<img width="600" alt="截屏2023-05-02 15 52 33" src="https://user-images.githubusercontent.com/20330940/235610318-2ef4e3f1-278b-4ca4-99f4-b38120efc395.png">

[![Version](https://img.shields.io/badge/Version-2.0--rc1-pink.svg?style=flat-square)]()
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)]()
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-linux-x86_64.yml?branch=refactor&label=Linux%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-macos-x86_64.yml?branch=refactor&label=macOS%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)

[![](https://img.shields.io/badge/Extension%20Counter-50+-yellow.svg?style=flat-square)]()
[![](https://img.shields.io/github/search/crazywhalecc/static-php-cli/TODO?label=TODO%20Counter&style=flat-square)]()

## Compilation Requirements

Yes, this project is written in PHP, pretty funny.
But static-php-cli runtime only requires an environment above PHP 8.0 and `tokenizer`, `iconv` extension.

Here is the architecture support status, where `CI` represents support for GitHub Action builds, 
`Local` represents support for local builds, and blank represents not currently supported.

|         | x86_64    | aarch64   |
|---------|-----------|-----------|
| macOS   | CI, Local | Local     |
| Linux   | CI, Local | CI, Local |
| Windows |           |           |

> macOS-arm64 is not supported for GitHub Actions, if you are going to build on arm, you can build it manually on your own machine.

Currently supported PHP versions for compilation are: `7.4`, `8.0`, `8.1`, `8.2`.

## Usage

Please first select the extension you want to compile based on the extension list below.

### Direct Download

If you don't compile yourself, you can download pre-compiled artifact from Actions, or from self-hosted server: [Here](https://dl.zhamao.xin/static-php-cli/)

> self-hosted server contains extensions: `bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip`

### Supported Extensions

[Supported Extension List](/ext-support.md)

> If there is no extension you need here, you can submit an issue.

### GitHub Actions Build

Use GitHub Action to easily build a statically compiled PHP and phpmicro, 
and at the same time define the extensions to be compiled by yourself.

1. Fork me.
2. Go to the Actions of the project and select `CI`.
3. Select `Run workflow`, fill in the PHP version you want to compile, the target type, and the list of extensions. (extensions comma separated, e.g. `bcmath,curl,mbstring`)
4. After waiting for about a period of time, enter the corresponding task and get `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

- When using ubuntu-latest, it will build linux-x86_64 binary.
- When using macos-latest, it will build macOS-x86_64 binary.

### Manual Build

Clone repo first:

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
```

If you have not installed php on your system, you can use package management to install PHP (such as brew, apt, yum, apk etc.).

And you can also download single-file php binary and composer using command `bin/setup-runtime`.
The PHP runtime for static-php-cli itself will be downloaded at `bin/php`, and composer is at `bin/composer`.

```bash
cd static-php-cli
chmod +x bin/setup-runtime
# It will download php-cli from self-hosted server and composer from getcomposer.org
./bin/setup-runtime

# Use this php runtime to run static-php-cli compiler
./bin/php bin/spc

# Use composer
./bin/php bin/composer
```

Basic usage for building php and micro with some extensions:

```bash
cd static-php-cli
composer update
chmod +x bin/spc
# Check system tool dependencies, fix them automatically (only support macOS) (TODO: Linux distro support)
./bin/spc doctor
# fetch all libraries
./bin/spc fetch --all
# with bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl extension, build both CLI and phpmicro SAPI
./bin/spc build bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl --build-cli --build-micro
```

You can also use the parameter `--with-php=x.y` to specify the downloaded PHP version, currently supports 7.4 ~ 8.2:

```bash
# Using PHP >= 8.0 is recommended, because 7.4 cannot use phpmicro
./bin/spc fetch --with-php=8.2 --all
```

Now we support `cli`, `micro`, `fpm`, you can use one or more of the following parameters to specify the compiled SAPI:

- `--build-cli`: build static cli executable
- `--build-micro`: build static phpmicro self-extracted executable
- `--build-fpm`: build static fpm binary
- `--build-all`: build all

If anything goes wrong, use `--debug` option to display full terminal output:

```bash
./bin/spc build openssl,pcntl,mbstring --debug --build-all
./bin/spc fetch --all --debug
```

In addition, we build NTS by default. If you are going to build ZTS version, just add `--enable-zts` option.

```bash
./bin/spc build openssl,pcntl --build-all --enable-zts
```

Adding option `--no-strip` can produce binaries with debug symbols, in order to debug (using gdb). Disabling strip will increase the size of static binary.

### php-cli Usage

> php-cli is a single static binary, you can use it like normal php installed on your system.

When using the parameter `--build-cli` or `--build-all`, 
the final compilation result will output a binary file named `./php`, 
which can be distributed and used directly. 
This file will be located in the directory `buildroot/bin/`, copy it out for use.

```bash
cd buildroot/bin/
./php -v                # check version
./php -m                # check extensions
./php your_code.php     # run your php code
./php your_project.phar # run your phar (project archive)
```

### micro.sfx Usage

> phpmicro is a SelF-extracted eXecutable SAPI module, 
> provided by [dixyes/phpmicro](https://github.com/dixyes/phpmicro). 
> It can put php runtime and your source code together.

When using the parameter `--build-all` or `--build-micro`, 
the final compilation result will output a file named `./micro.sfx`, 
which needs to be used with your PHP source code like `code.php`. 
This file will be located in the path `buildroot/bin/micro.sfx`, simply copy it out for use.

Prepare your project source code, which can be a single PHP file or a Phar file, for use.

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app

# If packing a PHAR file, replace code.php with the Phar file path.
```

> In some cases, PHAR files may not run in a micro environment.

### php-fpm Usage

When using the parameter `--build-all` or `--build-fpm`,
the final compilation result will output a file named `./php-fpm`,
This file will be located in the path `buildroot/bin/`, simply copy it out for use.

In normal Linux distributions and macOS systems, the package manager will automatically generate a default fpm configuration file after installing php-fpm.
Because php-fpm must specify a configuration file before running, the php-fpm compiled by this project will not have any configuration files, so you need to write `php-fpm.conf` and `pool.conf` configuration files yourself.

Specifying `php-fpm.conf` can use the command parameter `-y`, for example: `./php-fpm -y php-fpm.conf`.

## Current Status

- [X] Basic CLI framework (by `symfony/console`)
- [X] Linux support
- [X] macOS support
- [X] Exception handler
- [ ] Windows support
- [X] PHP 7.4 support
- [X] fpm support

More functions and features are coming soon, Bugs and TODOs: https://github.com/crazywhalecc/static-php-cli/issues/32

## Contribution

Currently, there are only a few supported extensions. 
If the extension you need is missing, you can create an issue. 
If you are familiar with this project, you are also welcome to initiate a pull request.

The basic principles for contributing are as follows:

- This project uses php-cs-fixer and phpstan as code formatting tools. Before contributing, please run `composer analyze` and `composer cs-fix` on the updated code.
- If other open source libraries are involved, the corresponding licenses should be provided. 
    Also, configuration files should be sorted using the command `sort-config` after modification.
    For more information about sorting commands, see the documentation.
- Naming conventions should be followed, such as using the extension name registered in PHP for the extension name itself, 
    and external library names should follow the project's own naming conventions. For internal logic functions, class names, variables, etc., 
    camelCase and underscore formats should be followed, and mixing within the same module is prohibited.
- When compiling external libraries and creating patches, compatibility with different operating systems should be considered.

## Sponsor this project

You can sponsor my project on [this page](https://github.com/crazywhalecc/crazywhalecc/blob/master/FUNDING.md).

## Open-Source License

This project is based on the tradition of using the MIT License for old versions, 
while the new version references source code from some other projects:

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs) (Mulun Permissive License)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli) (Apache 2.0 LICENSE+SWOOLE-CLI LICENSE)

Due to the special nature of this project, 
many other open source projects such as curl and protobuf will be used during the project compilation process, 
and they all have their own open source licenses.

Please use the `bin/spc dump-license` command to export the open source licenses used in the project after compilation, 
and comply with the corresponding project's LICENSE.

## Advanced

This project is pure open source project, and some modules are separated for developing.

This section will be improved after refactor version released.
