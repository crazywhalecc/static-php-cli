# static-php-cli

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Releases](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=main&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/crazywhalecc/static-php-cli/blob/main/LICENSE)

**static-php-cli** 是一个用于构建静态、独立 PHP 运行时的强大工具，支持众多流行扩展。

## 特性

- :elephant: **支持多 PHP 版本** - 支持 PHP 8.1, 8.2, 8.3, 8.4, 8.5
- :handbag: **单文件 PHP 可执行文件** - 构建零依赖的独立 PHP
- :hamburger: **phpmicro 集成** - 构建 **[phpmicro](https://github.com/dixyes/phpmicro)** 自解压可执行文件（将 PHP 二进制文件和源代码合并为一个文件）
- :pill: **智能环境检查器** - 自动构建环境检查器，具备自动修复功能
- :zap: **跨平台支持** - 支持 Linux、macOS、FreeBSD 和 Windows
- :wrench: **可配置补丁** - 可自定义的源代码补丁系统
- :books: **智能依赖管理** - 自动处理构建依赖
- 📦 **自包含工具** - 提供使用 [box](https://github.com/box-project/box) 构建的 `spc` 可执行文件
- :fire: **广泛的扩展支持** - 支持 75+ 流行 [扩展](https://static-php.dev/zh/guide/extensions.html)
- :floppy_disk: **UPX 压缩** - 减小二进制文件大小 30-50%（仅 Linux/Windows）

**单文件独立 php-cli：**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**使用 phpmicro 将 PHP 代码与 PHP 解释器结合：**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## 快速开始

### 1. 下载 spc 二进制文件

```bash
# Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64
# Windows (x86_64, win10 build 17063 或更高版本，请先安装 VS2022)
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe
```

对于 macOS 和 Linux，请先添加执行权限：

```bash
chmod +x ./spc
```

### 2. 构建静态 PHP

首先，创建一个 `craft.yml` 文件，并从 [扩展列表](https://static-php.dev/zh/guide/extensions.html) 或 [命令生成器](https://static-php.dev/zh/guide/cli-generator.html) 中指定要包含的扩展：

```yml
# PHP 版本支持：8.1, 8.2, 8.3, 8.4, 8.5
php-version: 8.4
# 在此处放置您的扩展列表
extensions: "apcu,bcmath,calendar,ctype,curl,dba,dom,exif,fileinfo,filter,gd,iconv,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,readline,redis,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib"
sapi:
  - cli
  - micro
  - fpm
download-options:
  prefer-pre-built: true
```

运行命令：

```bash
./spc craft

# 输出完整控制台日志
./spc craft --debug
```

### 3. 静态 PHP 使用

现在您可以将 static-php-cli 构建的二进制文件复制到另一台机器上，无需依赖即可运行：

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

## 文档

当前 README 包含基本用法。有关 static-php-cli 的所有功能，
请访问 <https://static-php.dev>。

## 直接下载

如果您不想构建或想先测试，可以从 [Actions](https://github.com/static-php/static-php-cli-hosted/actions/workflows/build-php-bulk.yml) 下载示例预编译工件，或从自托管服务器下载。

以下是几个具有不同扩展组合的预编译静态 PHP 二进制文件，
您可以根据需要直接下载。

| 组合名称                                                                 | 扩展数量                                                            | 系统           | 备注                 |
|----------------------------------------------------------------------|----------------------------------------------------------------------------|--------------|--------------------|
| [common](https://dl.static-php.dev/static-php-cli/common/)           | [30+](https://dl.static-php.dev/static-php-cli/common/README.txt)          | Linux, macOS | 二进制文件大小约为 7.5MB    |
| [bulk](https://dl.static-php.dev/static-php-cli/bulk/)               | [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt)            | Linux, macOS | 二进制文件大小约为 25MB     |
| [gnu-bulk](https://dl.static-php.dev/static-php-cli/gnu-bulk/)       | [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt)            | Linux, macOS | 使用 glibc 的 bulk 组合 |
| [minimal](https://dl.static-php.dev/static-php-cli/minimal/)         | [5](https://dl.static-php.dev/static-php-cli/minimal/README.txt)           | Linux, macOS | 二进制文件大小约为 3MB      |
| [spc-min](https://dl.static-php.dev/static-php-cli/windows/spc-min/) | [5](https://dl.static-php.dev/static-php-cli/windows/spc-min/README.txt)   | Windows      | 二进制文件大小约为 3MB      |
| [spc-max](https://dl.static-php.dev/static-php-cli/windows/spc-max/) | [40+](https://dl.static-php.dev/static-php-cli/windows/spc-max/README.txt) | Windows      | 二进制文件大小约为 8.5MB    |

> Linux 和 Windows 支持对二进制文件进行 UPX 压缩，可以将二进制文件大小减少 30% 到 50%。
> macOS 不支持 UPX 压缩，因此 mac 的预构建二进制文件大小较大。

### 在线构建（使用 GitHub Actions）

上方直接下载的二进制不能满足需求时，可使用 GitHub Action 可以轻松构建静态编译的 PHP，
同时自行定义要编译的扩展。

1. Fork 本项目。
2. 进入项目的 Actions 并选择 `CI`。
3. 选择 `Run workflow`，填入您要编译的 PHP 版本、目标类型和扩展列表。（扩展用逗号分隔，例如 `bcmath,curl,mbstring`）
4. 等待一段时间后，进入相应的任务并获取 `Artifacts`。

如果您启用 `debug`，构建时将输出所有日志，包括编译日志，以便故障排除。

## 贡献

如果您需要的扩展缺失，可以创建 issue。
如果您熟悉本项目，也欢迎发起 pull request。

如果您想贡献文档，请直接编辑 `docs/` 目录。

现在有一个 [static-php](https://github.com/static-php) 组织，用于存储与项目相关的仓库。

## 赞助本项目

您可以从 [GitHub Sponsor](https://github.com/crazywhalecc) 赞助我或我的项目。您捐赠的一部分将用于维护 **static-php.dev** 服务器。

**特别感谢以下赞助商**：

<a href="https://beyondco.de/"><img src="/docs/public/images/beyondcode-seeklogo.png" width="300" alt="Beyond Code Logo" /></a>

<a href="https://nativephp.com/"><img src="/docs/public/images/nativephp-logo.svg" width="300" alt="NativePHP Logo" /></a>

## 开源许可证

本项目本身基于 MIT 许可证，
一些新添加的扩展和依赖可能来自其他项目，
这些代码文件的头部也会给出额外的许可证和作者说明。

这些是类似的项目：

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)

本项目使用了 [dixyes/lwmbs](https://github.com/dixyes/lwmbs) 的一些代码，例如 Windows 静态构建目标和 libiconv 支持。
lwmbs 基于 [Mulan PSL 2](http://license.coscl.org.cn/MulanPSL2) 许可证。

由于本项目的特殊性，
项目编译过程中会使用许多其他开源项目，如 curl 和 protobuf，
它们都有自己的开源许可证。

请在编译后使用 `bin/spc dump-license` 命令导出项目中使用的开源许可证，
并遵守相应项目的 LICENSE。
