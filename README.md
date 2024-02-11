# static-php-cli

[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-white?style=flat-square)](README-zh.md)
[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-white?style=flat-square)](README.md)
[![Version](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)]()
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=main&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)]()
[![](https://img.shields.io/badge/Extension%20Counter-75+-yellow.svg?style=flat-square)]([https://static-php.dev/](https://static-php.dev/en/guide/extensions.html))
[![](https://dcbadge.vercel.app/api/server/RNpegEYW?style=flat-square&compact=true&theme=default-inverted)](https://discord.gg/RNpegEYW)

**static-php-cli** is a powerful tool designed for building static, standalone PHP runtime
with popular extensions.

Static PHP built by **static-php-cli** supports `cli`, `fpm`, `embed` and `micro` SAPI.

**static-php-cli** also has the ability to package PHP projects
along with the PHP interpreter into one single executable file.

## Features

static-php-cli (you can call it `spc`) has a lot of features:

- :handbag: Build single-file php executable, without any dependencies
- :hamburger: Build **[phpmicro](https://github.com/dixyes/phpmicro)** self-extracted executable (glue php binary and php source code into one file)
- :pill: Automatic build environment checker (Doctor module)
- :zap: `Linux`, `macOS`, `FreeBSD`, `Windows` support
- :wrench: Configurable source code patches
- :books: Build dependency management
- 📦 Provide `spc` own standalone executable (built by spc and [box](https://github.com/box-project/box))
- :fire: Support many popular [extensions](https://static-php.dev/en/guide/extensions.html)

**Single-file standalone php-cli:**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**Combine PHP code with PHP interpreter using phpmicro:**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## Documentation

The current README contains basic usage. For all the features of static-php-cli,
see <https://static-php.dev> .

## Direct Download

If you don't want to build or want to test first, you can download example pre-compiled artifact from [Actions](https://github.com/static-php/static-php-cli-hosted/actions/workflows/build-php-bulk.yml), or from self-hosted server.

Below are several precompiled static-php binaries with different extension combinations,
which can be downloaded directly according to your needs.

- [Extension-Combination - common](https://dl.static-php.dev/static-php-cli/common/): `common` contains about [30+](https://dl.static-php.dev/static-php-cli/common/README.txt) commonly used extensions, and the size is about 22MB.
- [Extension-Combination - bulk](https://dl.static-php.dev/static-php-cli/bulk/): `bulk` contains [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt) extensions and is about 70MB in size.
- [Extension-Combination - minimal](https://dl.static-php.dev/static-php-cli/minimal/): `minimal` contains [5](https://dl.static-php.dev/static-php-cli/minimal/README.txt) extensions and is about 6MB in size.

## Build

### Compilation Requirements

- PHP >= 8.1 (This is the version required by spc itself, not the build version)
- Extension: `mbstring,pcntl,posix,tokenizer,phar`
- Supported OS with `curl` and `git` installed

You can say I made a PHP builder written in PHP, pretty funny.
But static-php-cli runtime only requires an environment above PHP 8.1 and `mbstring`, `pcntl` extension.

Here is the supported OS and arch, where :octocat: represents support for GitHub Action builds,
:computer: represents support for local manual builds, and blank represents not currently supported.

|         | x86_64               | aarch64              |
|---------|----------------------|----------------------|
| macOS   | :octocat: :computer: | :octocat: :computer: |
| Linux   | :octocat: :computer: | :octocat: :computer: |
| Windows | :computer:           |                      |
| FreeBSD | :computer:           | :computer:           |

Currently supported PHP versions for compilation are: `7.3`, `7.4`, `8.0`, `8.1`, `8.2`, `8.3`.

### Supported Extensions

Please first select the extension you want to compile based on the extension list below.

- [Supported Extension List](https://static-php.dev/en/guide/extensions.html)
- [Command Generator](https://static-php.dev/en/guide/cli-generator.html)

> If an extension you need is missing, you can submit an issue.

Here is the current planned roadmap for extension support: [#152](https://github.com/crazywhalecc/static-php-cli/issues/152) .

### Build Online (using GitHub Actions)

Use GitHub Action to easily build a statically compiled PHP,
and at the same time define the extensions to be compiled by yourself.

1. Fork me.
2. Go to the Actions of the project and select `CI`.
3. Select `Run workflow`, fill in the PHP version you want to compile, the target type, and the list of extensions. (extensions comma separated, e.g. `bcmath,curl,mbstring`)
4. After waiting for about a period of time, enter the corresponding task and get `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

### Build Locally (using SPC binary)

This project provides a binary file of static-php-cli: `spc`.
You can use `spc` binary instead of installing any runtime like golang app.
Currently, the platforms supported by `spc` binary are Linux and macOS.

Download from self-hosted nightly builds using commands below:

```bash
# Download from self-hosted nightly builds (sync with main branch)
# For Linux x86_64
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# For Linux aarch64
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64

# add x perm
chmod +x ./spc
./spc --version
```

Self-hosted `spc` is built by GitHub Actions, you can also download from Actions artifacts [here](https://github.com/crazywhalecc/static-php-cli/actions/workflows/release-build.yml).

### Build Locally (using git source)

```bash
# just clone me!
git clone https://github.com/crazywhalecc/static-php-cli.git
```

If you have not installed php on your system, we recommend that you use the built-in setup-runtime to install PHP and Composer automatically.

```bash
cd static-php-cli
chmod +x bin/setup-runtime
# it will download static php (from self-hosted server) and composer (from getcomposer)
bin/setup-runtime
# initialize composer deps
bin/composer install
# chmod
chmod +x bin/spc
bin/spc --version
```

### Start Building PHP

Basic usage for building php with some extensions:

> If you are using the packaged `spc` binary, you need to replace `bin/spc` with `./spc` in the following commands.

```bash
# Check system tool dependencies, auto-fix them if possible
./bin/spc doctor --auto-fix

# fetch all libraries
./bin/spc download --all
# only fetch necessary sources by needed extensions (recommended)
./bin/spc download --for-extensions=openssl,pcntl,mbstring,pdo_sqlite
# download different PHP version (--with-php=x.y, recommend 7.3 ~ 8.3)
./bin/spc download --for-extensions=openssl,curl,mbstring --with-php=8.1

# with bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl extension, build both CLI and phpmicro SAPI
./bin/spc build bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl --build-cli --build-micro
# build thread-safe (ZTS) version (--enable-zts)
./bin/spc build curl,phar --enable-zts --build-cli
```

Now we support `cli`, `micro`, `fpm` and `embed` SAPI. You can use one or more of the following parameters to specify the compiled SAPI:

- `--build-cli`: build static cli executable
- `--build-micro`: build static phpmicro self-extracted executable
- `--build-fpm`: build static fpm binary
- `--build-embed`: build embed (libphp)
- `--build-all`: build all

If anything goes wrong, use `--debug` option to display full terminal output:

```bash
./bin/spc build openssl,pcntl,mbstring --debug --build-all
./bin/spc download --all --debug
```

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

> In some cases, PHAR files may not run in a micro environment. Overall, micro is not production ready.

### Use fpm

When using the parameter `--build-all` or `--build-fpm`,
the final compilation result will output a file named `./php-fpm`,
This file will be located in the path `buildroot/bin/`, simply copy it out for use.

In common Linux distributions and macOS systems, the package manager will automatically generate a default fpm configuration file after installing php-fpm.
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

## Sponsor this project

You can sponsor my project on [this page](https://github.com/crazywhalecc/crazywhalecc/blob/master/FUNDING.md).

## Open-Source License

This project itself is based on MIT License,
some newly added extensions and dependencies may originate from the the other projects,
and the headers of these code files will also be given additional instructions LICENSE and AUTHOR.

These are similar projects:

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)

The project uses some code from [dixyes/lwmbs](https://github.com/dixyes/lwmbs), such as windows static build target and libiconv support.
lwmbs is licensed under the [Mulan PSL 2](http://license.coscl.org.cn/MulanPSL2).

Due to the special nature of this project,
many other open source projects such as curl and protobuf will be used during the project compilation process,
and they all have their own open source licenses.

Please use the `bin/spc dump-license` command to export the open source licenses used in the project after compilation,
and comply with the corresponding project's LICENSE.
