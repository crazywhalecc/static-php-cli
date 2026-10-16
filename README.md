# StaticPHP

[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-moccasin?style=flat-square)](README.md)
[![Releases](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=v3&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/crazywhalecc/static-php-cli/blob/v3/LICENSE)
[![Discord](https://img.shields.io/discord/nrSRbpMJ?label=Discord&logo=discord&style=flat-square)](https://discord.gg/xf6Rd4pEAk)

**StaticPHP** is a powerful tool designed for building portable executables including PHP, extensions, and more.

> [!IMPORTANT]
> **v3** is the current default branch. The project is now called **StaticPHP**, while the repository keeps its `static-php-cli` name.
> For v2, please check the [v2 branch](https://github.com/crazywhalecc/static-php-cli/tree/main).
> Stay tuned for the official release.

## Features

- :elephant: Support multiple PHP versions - PHP 8.1, 8.2, 8.3, 8.4, 8.5, 8.6 (pre-release)
- :handbag: Build single-file PHP executable with zero dependencies
- :hamburger: Build **[phpmicro](https://github.com/static-php/phpmicro)** self-extracting executables (combines PHP binary and source code into one file)
- :pill: Automatic build environment checker with auto-fix capabilities
- :zap: `Linux`, `macOS`, `Windows` support
- :wrench: Easy to extend with vendor mode and custom registries
- :books: Intelligent dependency management
- 📦 Self-contained `spc` executable for easy self-installation
- :fire: Support 100+ popular [PHP extensions](https://static-php.dev/en/guide/extensions.html)
- :floppy_disk: UPX compression support (reduces binary size by 30-50%)

**Single-file standalone php-cli:**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**Combine PHP code with PHP interpreter using phpmicro:**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## Quickstart

### 1. Download spc binary

```bash
# For Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64
# For Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-aarch64
# Windows (x86_64, Windows 10 build 17063 or later, install Visual Studio 2022 with C++ tools first)
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/v3/spc-bin/nightly/spc-windows-x64.exe
```

For macOS and Linux, add execute permission first:

```bash
chmod +x ./spc
```

> [!TIP]
> Before the first build, it is recommended to run `./spc doctor --auto-fix` to check and install the required build dependencies (spc will also remind you automatically when needed).

### 2. Build Static PHP

First, create a `craft.yml` file and specify which extensions you want to include from [extension list](https://static-php.dev/en/guide/extensions.html) or [command generator](https://static-php.dev/en/guide/cli-generator.html):

```yml
# PHP version support: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6 (pre-release)
php-version: 8.5
# Put your extension list here
extensions: "apcu,bcmath,calendar,ctype,curl,dba,dom,exif,fileinfo,filter,gd,iconv,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,readline,redis,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib"
sapi:
  - cli
  - micro
download-options:
  parallel: 10
```

Run command:

```bash
./spc craft

# Output full console log
./spc craft -vvv
```

### 3. Static PHP usage

Now you can copy binaries built by StaticPHP to another machine and run with no dependencies:

```
# php-cli
buildroot/bin/php -v

# phpmicro
echo '<?php echo "Hello world!\n";' > a.php
./spc micro:combine a.php -O my-app
./my-app
```

## Documentation

The current README contains basic usage. For the complete feature set of StaticPHP,
see <https://static-php.dev>.

If you are migrating from v2, check out the [migration guide](https://static-php.dev/en/guide/migrate-from-v2.html).

## Direct Download

If you do not want to build yet or just want to test first, you can download example pre-compiled artifacts from [Actions](https://github.com/static-php/hosted/actions/workflows/v3-php-bin-unix.yml) ([Windows](https://github.com/static-php/hosted/actions/workflows/v3-php-bin-windows.yml)) or from a self-hosted server.

Below are several precompiled static-php binaries with different extension combinations,
which can be downloaded directly according to your needs.

| Combination                                                | Extension Count                                                  | OS           | Comment                         |
|------------------------------------------------------------|------------------------------------------------------------------|--------------|---------------------------------|
| [common](https://dl.static-php.dev/v3/php-bin/common/)     | [37](https://dl.static-php.dev/v3/php-bin/common/README.txt)     | Linux, macOS | The binary size is about 13MB   |
| [bulk](https://dl.static-php.dev/v3/php-bin/bulk/)         | [56](https://dl.static-php.dev/v3/php-bin/bulk/README.txt)       | Linux, macOS | The binary size is about 30MB   |
| [gnu-bulk](https://dl.static-php.dev/v3/php-bin/gnu-bulk/) | [56](https://dl.static-php.dev/v3/php-bin/gnu-bulk/README.txt)   | Linux        | Using shared glibc              |
| [minimal](https://dl.static-php.dev/v3/php-bin/minimal/)   | [7](https://dl.static-php.dev/v3/php-bin/minimal/README.txt)     | Linux, macOS | The binary size is about 3MB    |
| [spc-min](https://dl.static-php.dev/v3/php-bin/spc-min/)   | [7](https://dl.static-php.dev/v3/php-bin/spc-min/README.txt)     | Windows      | The binary size is about 3MB    |
| [spc-max](https://dl.static-php.dev/v3/php-bin/spc-max/)   | [50](https://dl.static-php.dev/v3/php-bin/spc-max/README.txt)    | Windows      | The binary size is about 9MB    |

> Linux and Windows binaries are compressed with UPX, which can reduce the size of the binary by 30% to 50%.
> macOS does not support UPX compression, so the size of the pre-built binaries for macOS is larger.

### Build Online (using GitHub Actions)

When the direct-download binaries above cannot meet your needs,
you can use GitHub Actions to easily build a statically compiled PHP
while defining your own extension list.

1. Fork this repository.
2. Go to the Actions of the project and select `CI on Unix` (or `CI on x86_64 Windows` for Windows builds).
3. Select `Run workflow`, fill in the target OS (`os`), the PHP version (`php-version`), and the list of extensions (`extensions`, comma separated, e.g. `bcmath,curl,mbstring`).
4. After waiting for the workflow to finish, open the corresponding run and download `Artifacts`.

If you enable `debug`, all logs will be output at build time, including compiled logs, for troubleshooting.

> We are also planning to provide a reusable GitHub Actions workflow in the future, 
> so that you can easily build static PHP in your own repository, without forking this project.

## Contribution

If the extension you need is missing, you can create an issue.
If you are familiar with this project, you are also welcome to initiate a pull request.

If you want to contribute documentation, please just edit in `docs/`.

## Sponsor this project

You can sponsor me or my project from [GitHub Sponsor](https://github.com/crazywhalecc). A portion of your donation will be used to maintain the **static-php.dev** server.

**Special thanks to sponsors below**:

<a href="https://beyondco.de/"><img src="/docs/public/images/beyondcode-seeklogo.png" width="300" alt="Beyond Code Logo" /></a>

<a href="https://nativephp.com/"><img src="/docs/public/images/nativephp-logo.svg" width="300" alt="NativePHP Logo" /></a>

<img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.png" alt="JetBrains Logo" width="96" />

**[JetBrains](https://github.com/JetBrains)** provides core contributors with <a href="https://www.jetbrains.com/community/opensource">OSS Development Licenses</a>

## Open-Source License

This project itself is licensed under MIT.
Some newly added extensions and dependencies may originate from other projects.
The headers of those source files may also include additional LICENSE and AUTHOR information.

Please use the `bin/spc dump-license` command to export the open source licenses used in the project after compilation,
and comply with the corresponding project's LICENSE.
