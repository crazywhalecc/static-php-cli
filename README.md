# static-php-cli

Build single static PHP binary, with PHP project together, with popular extensions included.

🌐 **[中文](README-zh.md)** | **[English](README.md)**

The project name is static-php-cli, but it actually supports cli, fpm, micro and embed SAPI 😎

Compile a purely static php-cli binary file with various extensions to make PHP applications more portable! (cli SAPI)

<img width="600" alt="2023-05-02 15 53 13" src="https://user-images.githubusercontent.com/20330940/235610282-23e58d68-bd35-4092-8465-171cff2d5ba8.png">

You can also use the micro binary file to combine php binary and php source code into one for distribution! (micro SAPI)

<img width="600" alt="2023-05-02 15 52 33" src="https://user-images.githubusercontent.com/20330940/235610318-2ef4e3f1-278b-4ca4-99f4-b38120efc395.png">

> This SAPI feature is from the [Fork](https://github.com/static-php/phpmicro) of [dixyes/phpmicro](https://github.com/dixyes/phpmicro).

[![Version](https://img.shields.io/badge/Version-2.0--rc8-pink.svg?style=flat-square)]()
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)]()
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-linux-x86_64.yml?branch=refactor&label=Linux%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-macos-x86_64.yml?branch=refactor&label=macOS%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)

[![](https://img.shields.io/badge/Extension%20Counter-65+-yellow.svg?style=flat-square)]()
[![](https://img.shields.io/github/search/crazywhalecc/static-php-cli/TODO?label=TODO%20Counter&style=flat-square)]()

## Docs

The current README contains basic usage. For all the features of static-php-cli,
see <https://static-php.dev> .

## Direct Download

If you don't want to compile yourself, you can download example pre-compiled artifact from [Actions](https://github.com/static-php/static-php-cli-hosted/actions/workflows/build-php-common.yml), or from [self-hosted server](https://dl.static-php.dev/static-php-cli/common/).

> self-hosted server contains extensions: `bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip`

## Use static-php-cli to build PHP

### Compilation Requirements

Yes, this project is written in PHP, pretty funny.
But static-php-cli runtime only requires an environment above PHP 8.1 and `mbstring`, `pcntl` extension.

Here is the architecture support status, where :octocat: represents support for GitHub Action builds,
:computer: represents support for local manual builds, and blank represents not currently supported.

|         | x86_64               | aarch64              |
|---------|----------------------|----------------------|
| macOS   | :octocat: :computer: | :computer:           |
| Linux   | :octocat: :computer: | :octocat: :computer: |
| Windows |                      |                      |
| FreeBSD | :computer:           | :computer:           |

> macOS-arm64 is not supported for GitHub Actions, if you are going to build on arm, you can build it manually on your own machine.

Currently supported PHP versions for compilation are: `7.3`, `7.4`, `8.0`, `8.1`, `8.2`, `8.3`.

### Supported Extensions

Please first select the extension you want to compile based on the extension list below.

- [Supported Extension List](https://static-php.dev/en/guide/extensions.html)
- [Command Generator](https://static-php.dev/en/guide/cli-generator.html)

> If an extension you need is missing, you can submit an issue.

### GitHub Actions Build

Use GitHub Action to easily build a statically compiled PHP,
and at the same time define the extensions to be compiled by yourself.

1. Fork me.
2. Go to the Actions of the project and select `CI`.
3. Select `Run workflow`, fill in the PHP version you want to compile, the target type, and the list of extensions. (extensions comma separated, e.g. `bcmath,curl,mbstring`)
4. After waiting for about a period of time, enter the corresponding task and get `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

- When using ubuntu-latest, it will build linux-x86_64 binary.
- When using macos-latest, it will build macOS-x86_64 binary.

### Manual build (using SPC binary)

This project provides a binary file of static-php-cli.
You can directly download the binary file of the corresponding platform and then use it to build static PHP.
Currently, the platforms supported by `spc` binary are Linux and macOS.

Here's how to download from GitHub Actions:

1. Enter [GitHub Actions](https://github.com/crazywhalecc/static-php-cli/actions/workflows/release-build.yml).
2. Select the latest build task, select `Artifacts`, and download the binary file of the corresponding platform.
3. Unzip the `.zip` file. After decompressing, add execution permissions to it: `chmod +x ./spc`.

You can also download binaries from a self-hosted server: [enter](https://dl.static-php.dev/static-php-cli/spc-bin/nightly/).

> SPC single-file binary is built by phpmicro and box.

### Manual build (using source code)

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

# Initialize this project
cd static-php-cli
composer update
chmod +x bin/spc
```

### Use static-php-cli

Basic usage for building php and micro with some extensions:

> If you are using the packaged `spc` binary, you need to replace `bin/spc` with `./spc` in the following commands.

```bash
# Check system tool dependencies, fix them automatically
./bin/spc doctor
# fetch all libraries
./bin/spc download --all
# only fetch necessary sources by needed extensions
./bin/spc download --for-extensions=openssl,pcntl,mbstring,pdo_sqlite
# with bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl extension, build both CLI and phpmicro SAPI
./bin/spc build bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl --build-cli --build-micro
```

You can also use the parameter `--with-php=x.y` to specify the downloaded PHP version, currently supports 7.4 ~ 8.2:

```bash
# Using PHP >= 8.0 is recommended, because PHP7 cannot use phpmicro
./bin/spc fetch --with-php=8.2 --all
```

Now we support `cli`, `micro`, `fpm`, you can use one or more of the following parameters to specify the compiled SAPI:

- `--build-cli`: build static cli executable
- `--build-micro`: build static phpmicro self-extracted executable
- `--build-fpm`: build static fpm binary
- `--build-embed`: build embed (libphp)
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

## Different SAPI Usage

### Use cli

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

### Use micro

> phpmicro is a SelF-extracted eXecutable SAPI module,
> provided by [phpmicro](https://github.com/dixyes/phpmicro) project.
> But this project is using a [fork](https://github.com/static-php/phpmicro) of phpmicro, because we need to add some features to it.
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
```

If you package a PHAR file, just replace `code.php` with the phar file path.
You can use [box-project/box](https://github.com/box-project/box) to package your CLI project as Phar,
It is then combined with phpmicro to produce a standalone executable binary.

```bash
# Use the micro.sfx generated by static-php-cli to combine,
bin/spc micro:combine my-app.phar
# or you can directly use the cat command to combine them.
cat buildroot/bin/micro.sfx my-app.phar > my-app && chmod +x my-app

# Use micro:combine combination to inject INI options into the binary.
bin/spc micro:combine my-app.phar -I "memory_limit=4G" -I "disable_functions=system" --output my-app-2
```

> In some cases, PHAR files may not run in a micro environment.

### Use fpm

When using the parameter `--build-all` or `--build-fpm`,
the final compilation result will output a file named `./php-fpm`,
This file will be located in the path `buildroot/bin/`, simply copy it out for use.

In normal Linux distributions and macOS systems, the package manager will automatically generate a default fpm configuration file after installing php-fpm.
Because php-fpm must specify a configuration file before running, the php-fpm compiled by this project will not have any configuration files, so you need to write `php-fpm.conf` and `pool.conf` configuration files yourself.

Specifying `php-fpm.conf` can use the command parameter `-y`, for example: `./php-fpm -y php-fpm.conf`.

### Use embed

When using the project parameters `--build-embed` or `--build-all`,
the final compilation result will output a `libphp.a`, `php-config` and a series of header files,
stored in `buildroot/`. You can introduce them in your other projects.

If you know [embed SAPI](https://github.com/php/php-src/tree/master/sapi/embed), you should know how to use it.
You may require the introduction of other libraries during compilation,
you can use `buildroot/bin/php-config` to obtain the compile-time configuration.

For an advanced example of how to use this feature, take a look at [how to use it to build a static version of FrankenPHP](https://github.com/dunglas/frankenphp/blob/main/docs/static.md).

## Contribution

If the extension you need is missing, you can create an issue.
If you are familiar with this project, you are also welcome to initiate a pull request.

If you want to contribute documentation, please go to [static-php/static-php-cli-docs](https://github.com/static-php/static-php-cli-docs).

Now there is a [static-php](https://github.com/static-php) organization, which is used to store the repo related to the project.

Part of the English document is written by me, and part is translated by Google,
and there may be inaccurate descriptions, strange or offensive expressions.
If you are a native English speaker, some corrections to the documentation are welcome.

## Sponsor this project

You can sponsor my project on [this page](https://github.com/crazywhalecc/crazywhalecc/blob/master/FUNDING.md).

## Open-Source License

This project itself is based on MIT License,
some newly added extensions and dependencies may originate from the following projects (including but not limited to),
and the headers of these code files will also be given additional instructions LICENSE and AUTHOR:

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs) (Mulun Permissive License)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli) (Apache 2.0 LICENSE+SWOOLE-CLI LICENSE)

Due to the special nature of this project,
many other open source projects such as curl and protobuf will be used during the project compilation process,
and they all have their own open source licenses.

Please use the `bin/spc dump-license` command to export the open source licenses used in the project after compilation,
and comply with the corresponding project's LICENSE.

## Advanced

The refactoring branch of this project is written modularly.
If you are interested in this project and want to join the development,
you can refer to the [Contribution Guide](https://static-php.dev) of the documentation to contribute code or documentation.
