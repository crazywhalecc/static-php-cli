# static-php-cli

[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-moccasin?style=flat-square)](README.md)
[![Releases](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=main&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/crazywhalecc/static-php-cli/blob/main/LICENSE)

**static-php-cli** is a powerful tool designed for building static, standalone PHP runtime
with popular extensions.

## Features

- :elephant: Support multiple PHP versions - PHP 8.1, 8.2, 8.3, 8.4, 8.5
- :handbag: Build single-file PHP executable with zero dependencies
- :hamburger:Build **[phpmicro](https://github.com/dixyes/phpmicro)** self-extracting executables (combines PHP binary and source code into one file)
- :pill: Automatic build environment checker with auto-fix capabilities
- :zap: `Linux`, `macOS`, `FreeBSD`, `Windows` support
- :wrench: Configurable source code patching
- :books: Intelligent dependency management
- 📦 Self-contained `spc` executable (built with [box](https://github.com/box-project/box))
- :fire: Support 75+ popular [extensions](https://static-php.dev/en/guide/extensions.html)
- :floppy_disk: UPX compression support (reduces binary size by 30-50%)

**Single-file standalone php-cli:**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**Combine PHP code with PHP interpreter using phpmicro:**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## Quickstart

### 1. Download spc binary

```bash
# For Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# For Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64
# Windows (x86_64, win10 build 17063 or later, please install VS2022 first)
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe
```

For macOS and Linux, add execute permission first:

```bash
chmod +x ./spc
```

### 2. Build Static PHP

First, create a `craft.yml` file and specify which extensions you want to include from [extension list](https://static-php.dev/en/guide/extensions.html) or [command generator](https://static-php.dev/en/guide/cli-generator.html):

```yml
# PHP version support: 8.1, 8.2, 8.3, 8.4, 8.5
php-version: 8.4
# Put your extension list here
extensions: "apcu,bcmath,calendar,ctype,curl,dba,dom,exif,fileinfo,filter,gd,iconv,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,readline,redis,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib"
sapi:
  - cli
  - micro
  - fpm
download-options:
  prefer-pre-built: true
```

Run command:

```bash
./spc craft

# Output full console log
./spc craft --debug
```

### 3. Static PHP usage

Now you can copy binaries built by static-php-cli to another machine and run with no dependencies:

```
# php-cli
buildroot/bin/php -v

# phpmicro
echo '<?php echo "Hello world!\n";' > a.php
./spc micro:combine a.php -O my-app
./my-app

# php-fpm
buildroot/bin/php-fpm -v
```

## Documentation

The current README contains basic usage. For all the features of static-php-cli,
see <https://static-php.dev> .

## Direct Download

If you don't want to build or want to test first, you can download example pre-compiled artifact from [Actions](https://github.com/static-php/static-php-cli-hosted/actions/workflows/build-php-bulk.yml), or from self-hosted server.

Below are several precompiled static-php binaries with different extension combinations,
which can be downloaded directly according to your needs.

| Combination                                                          | Extension Count                                                            | OS           | Comment                        |
|----------------------------------------------------------------------|----------------------------------------------------------------------------|--------------|--------------------------------|
| [common](https://dl.static-php.dev/static-php-cli/common/)           | [30+](https://dl.static-php.dev/static-php-cli/common/README.txt)          | Linux, macOS | The binary size is about 7.5MB |
| [bulk](https://dl.static-php.dev/static-php-cli/bulk/)               | [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt)            | Linux, macOS | The binary size is about 25MB  |
| [gnu-bulk](https://dl.static-php.dev/static-php-cli/gnu-bulk/)       | [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt)            | Linux, macOS | Using shared glibc             |
| [minimal](https://dl.static-php.dev/static-php-cli/minimal/)         | [5](https://dl.static-php.dev/static-php-cli/minimal/README.txt)           | Linux, macOS | The binary size is about 3MB   |
| [spc-min](https://dl.static-php.dev/static-php-cli/windows/spc-min/) | [5](https://dl.static-php.dev/static-php-cli/windows/spc-min/README.txt)   | Windows      | The binary size is about 3MB   |
| [spc-max](https://dl.static-php.dev/static-php-cli/windows/spc-max/) | [40+](https://dl.static-php.dev/static-php-cli/windows/spc-max/README.txt) | Windows      | The binary size is about 8.5MB |

> Linux and Windows supports UPX compression for binaries, which can reduce the size of the binary by 30% to 50%.
> macOS does not support UPX compression, so the size of the pre-built binaries for mac is larger.

### Build Online (using GitHub Actions)

When the above direct download binaries cannot meet your needs,
you can use GitHub Action to easily build a statically compiled PHP,
and at the same time define the extensions to be compiled by yourself.

1. Fork me.
2. Go to the Actions of the project and select `CI`.
3. Select `Run workflow`, fill in the PHP version you want to compile, the target type, and the list of extensions. (extensions comma separated, e.g. `bcmath,curl,mbstring`)
4. After waiting for about a period of time, enter the corresponding task and get `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

## Contribution

If the extension you need is missing, you can create an issue.
If you are familiar with this project, you are also welcome to initiate a pull request.

If you want to contribute documentation, please just edit in `docs/`.

Now there is a [static-php](https://github.com/static-php) organization, which is used to store the repo related to the project.

## Sponsor this project

You can sponsor me or my project from [GitHub Sponsor](https://github.com/crazywhalecc). A portion of your donation will be used to maintain the **static-php.dev** server.

**Special thanks to sponsors below**:

<a href="https://beyondco.de/"><img src="/docs/public/images/beyondcode-seeklogo.png" width="300" alt="Beyond Code Logo" /></a>

<a href="https://nativephp.com/"><img src="/docs/public/images/nativephp-logo.svg" width="300" alt="NativePHP Logo" /></a>

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
