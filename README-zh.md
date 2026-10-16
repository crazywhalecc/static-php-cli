# StaticPHP

[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-moccasin?style=flat-square)](README.md)
[![Releases](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=v3&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/crazywhalecc/static-php-cli/blob/v3/LICENSE)
[![Discord](https://img.shields.io/discord/nrSRbpMJ?label=Discord&logo=discord&style=flat-square)](https://discord.gg/xf6Rd4pEAk)

**StaticPHP** 是一个强大的工具，用于构建可移植的可执行文件，包括 PHP、扩展等。

> [!IMPORTANT]
> **v3** 是当前默认分支。项目名称现为 **StaticPHP**，仓库名保持 `static-php-cli` 不变。
> v2 版本请查看 [v2 分支](https://github.com/crazywhalecc/static-php-cli/tree/main)。
> 敬请关注正式版发布。

## 特性

- :elephant: 支持多个 PHP 版本 - PHP 8.1, 8.2, 8.3, 8.4, 8.5, 8.6（预发布）
- :handbag: 构建零依赖的单文件 PHP 可执行程序
- :hamburger: 构建 **[phpmicro](https://github.com/static-php/phpmicro)** 自解压可执行文件（将 PHP 二进制和源码合并为单个文件）
- :pill: 自动构建环境检查器，支持自动修复
- :zap: 支持 `Linux`、`macOS`、`Windows`
- :wrench: 通过 vendor 模式和自定义注册表实现便捷扩展
- :books: 智能依赖管理
- 📦 自包含 `spc` 可执行文件，便于自安装
- :fire: 支持 100+ 热门 [PHP 扩展](https://static-php.dev/en/guide/extensions.html)
- :floppy_disk: 支持 UPX 压缩（二进制体积可缩小 30-50%）

**单文件独立 php-cli：**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**使用 phpmicro 将 PHP 代码与 PHP 解释器结合：**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## 快速开始

### 1. 下载 spc 二进制

```bash
# Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64
# Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64（Intel）
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64（Apple）
curl -fsSL -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-aarch64
# Windows（x86_64，需 Windows 10 build 17063 或更高版本，请先安装带 C++ 工具的 Visual Studio 2022）
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/v3/spc-bin/nightly/spc-windows-x64.exe
```

对于 macOS 和 Linux，请先添加可执行权限：

```bash
chmod +x ./spc
```

> [!TIP]
> 建议在首次构建前运行 `./spc doctor --auto-fix`，检查并安装所需的构建依赖（必要时 spc 也会自动提醒你）。

### 2. 构建静态 PHP

首先，创建 `craft.yml` 文件，并从 [扩展列表](https://static-php.dev/en/guide/extensions.html) 或 [命令生成器](https://static-php.dev/en/guide/cli-generator.html) 指定要包含的扩展：

```yml
# PHP 版本支持：8.1、8.2、8.3、8.4、8.5、8.6（预发布）
php-version: 8.5
# 在此填写你的扩展列表
extensions: "apcu,bcmath,calendar,ctype,curl,dba,dom,exif,fileinfo,filter,gd,iconv,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,readline,redis,session,simplexml,sockets,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib"
sapi:
  - cli
  - micro
download-options:
  parallel: 10
```

运行命令：

```bash
./spc craft

# 输出完整控制台日志
./spc craft -vvv
```

### 3. 静态 PHP 使用

现在你可以将 StaticPHP 构建的二进制复制到另一台机器并在无依赖环境下运行：

```
# php-cli
buildroot/bin/php -v

# phpmicro
echo '<?php echo "Hello world!\n";' > a.php
./spc micro:combine a.php -O my-app
./my-app
```

## 文档

当前 README 包含基础用法。有关 StaticPHP 的完整功能集，
请访问 <https://static-php.dev>。

如果你从 v2 迁移，请参阅 [迁移指南](https://static-php.dev/zh/guide/migrate-from-v2.html)。

## 直接下载

如果你暂时不想构建，或只想先测试，可以从 [Actions](https://github.com/static-php/hosted/actions/workflows/v3-php-bin-unix.yml)（[Windows](https://github.com/static-php/hosted/actions/workflows/v3-php-bin-windows.yml)）下载示例预编译产物，或从自托管服务器下载。

以下是几个具有不同扩展组合的预编译静态 PHP 二进制文件，
你可以根据需要直接下载。

| 组合名称                                                  | 扩展数量                                                    | 系统           | 备注                         |
|-----------------------------------------------------------|-------------------------------------------------------------|----------------|------------------------------|
| [common](https://dl.static-php.dev/v3/php-bin/common/)     | [37](https://dl.static-php.dev/v3/php-bin/common/README.txt)     | Linux, macOS | 二进制文件大小约为 13MB    |
| [bulk](https://dl.static-php.dev/v3/php-bin/bulk/)         | [56](https://dl.static-php.dev/v3/php-bin/bulk/README.txt)       | Linux, macOS | 二进制文件大小约为 30MB    |
| [gnu-bulk](https://dl.static-php.dev/v3/php-bin/gnu-bulk/) | [56](https://dl.static-php.dev/v3/php-bin/gnu-bulk/README.txt)   | Linux        | 使用共享 glibc             |
| [minimal](https://dl.static-php.dev/v3/php-bin/minimal/)   | [7](https://dl.static-php.dev/v3/php-bin/minimal/README.txt)     | Linux, macOS | 二进制文件大小约为 3MB     |
| [spc-min](https://dl.static-php.dev/v3/php-bin/spc-min/)   | [7](https://dl.static-php.dev/v3/php-bin/spc-min/README.txt)     | Windows      | 二进制文件大小约为 3MB     |
| [spc-max](https://dl.static-php.dev/v3/php-bin/spc-max/)   | [50](https://dl.static-php.dev/v3/php-bin/spc-max/README.txt)    | Windows      | 二进制文件大小约为 9MB     |

> Linux 和 Windows 二进制文件均经过 UPX 压缩，可以将二进制文件大小减少 30% 到 50%。
> macOS 不支持 UPX 压缩，因此 mac 的预构建二进制文件大小较大。

### 在线构建（使用 GitHub Actions）

当上方直接下载的二进制无法满足你的需求时，
你可以使用 GitHub Actions 轻松构建静态编译的 PHP，
并同时自定义要编译的扩展列表。

1. Fork 此仓库。
2. 进入项目的 Actions 并选择 `CI on Unix`（Windows 构建请选择 `CI on x86_64 Windows`）。
3. 选择 `Run workflow`，填写目标系统（`os`）、PHP 版本（`php-version`）和扩展列表（`extensions`，扩展用逗号分隔，例如 `bcmath,curl,mbstring`）。
4. 等待工作流执行完成后，进入对应运行记录并下载 `Artifacts`。

如果你启用 `debug`，构建时将输出所有日志，包括编译日志，便于排查问题。

> 我们也计划在未来提供可复用的 GitHub Actions 工作流，
> 这样你无需 fork 本项目，也能在自己的仓库中轻松构建 static PHP。

## 贡献

如果你需要的扩展缺失，可以创建 issue。
如果你熟悉本项目，也欢迎发起 pull request。

如果你想贡献文档，请直接编辑 `docs/`。

## 赞助本项目

你可以通过 [GitHub Sponsor](https://github.com/crazywhalecc) 赞助我或我的项目。你捐赠的一部分将用于维护 **static-php.dev** 服务器。

**特别感谢以下赞助商：**

<a href="https://beyondco.de/"><img src="/docs/public/images/beyondcode-seeklogo.png" width="300" alt="Beyond Code Logo" /></a>

<a href="https://nativephp.com/"><img src="/docs/public/images/nativephp-logo.svg" width="300" alt="NativePHP Logo" /></a>

<img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.png" alt="JetBrains Logo" width="96" />

**[JetBrains](https://github.com/JetBrains)** 为核心贡献者提供 <a href="https://www.jetbrains.com/community/opensource">开源开发许可证</a>

## 开源许可证

本项目本身采用 MIT 许可证。
一些新添加的扩展和依赖可能来自其他项目。
这些源码文件头部也可能包含额外的 LICENSE 和 AUTHOR 信息。

请在编译后使用 `bin/spc dump-license` 命令导出项目中使用的开源许可证，
并遵守对应项目的 LICENSE。
