# static-php-cli

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%AC%F0%9F%87%A7-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Releases](https://img.shields.io/packagist/v/crazywhalecc/static-php-cli?include_prereleases&label=Release&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/releases)
[![CI](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/tests.yml?branch=main&label=Build%20Test&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://github.com/crazywhalecc/static-php-cli/blob/main/LICENSE)
[![Extensions](https://img.shields.io/badge/Extension%20Counter-75+-yellow.svg?style=flat-square)](https://static-php.dev/zh/guide/extensions.html)

**static-php-cli**是一个用于静态编译、构建 PHP 解释器的工具，支持众多流行扩展。

目前 static-php-cli 支持 `cli`、`fpm`、`embed` 和 `micro` SAPI。

**static-php-cli**也支持将 PHP 代码和 PHP 运行时打包为一个文件并运行。

## 特性

static-php-cli（简称 `spc`）有许多特性：

- :handbag: 构建独立的单文件 PHP 解释器，无需任何依赖
- :hamburger: 构建 **[phpmicro](https://github.com/dixyes/phpmicro)** 自执行二进制（将 PHP 代码和 PHP 解释器打包为一个文件）
- :pill: 提供一键检查和修复编译环境的 Doctor 模块
- :zap: 支持多个系统：`Linux`、`macOS`、`FreeBSD`、`Windows`
- :wrench: 高度自定义的代码 patch 功能
- :books: 自带编译依赖管理
- 📦 提供由自身编译的独立 `spc` 二进制（使用 spc 和 [box](https://github.com/box-project/box) 构建）
- :fire: 支持大量 [扩展](https://static-php.dev/zh/guide/extensions.html)
- :floppy_disk: 整合 UPX 工具（减小二进制文件体积）

**静态 php-cli:**

<img width="700" alt="out1" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/01a2e60f-13b0-4242-a645-f7afa4936396">

**使用 phpmicro 打包 PHP 代码:**

<img width="700" alt="out2" src="https://github.com/crazywhalecc/static-php-cli/assets/20330940/46b7128d-fb72-4169-957e-48564c3ff3e2">

## 文档

目前 README 编写了基本用法。有关 static-php-cli 所有的功能，请点击这里查看文档：<https://static-php.dev>。

## 直接下载

如果你不想自行编译 PHP，可以从本项目现有的示例 Action 下载 Artifact，也可以从自托管的服务器下载。

| 组合名称                                                                | 组合扩展数                                                                      | 系统          | 备注           |
|---------------------------------------------------------------------|----------------------------------------------------------------------------|-------------|--------------|
| [common](https://dl.static-php.dev/static-php-cli/common/)          | [30+](https://dl.static-php.dev/static-php-cli/common/README.txt)          | Linux/macOS | 体积为 7.5MB 左右 |
| [bulk](https://dl.static-php.dev/static-php-cli/bulk/)              | [50+](https://dl.static-php.dev/static-php-cli/bulk/README.txt)            | Linux/macOS | 体积为 25MB 左右  |
| [minimal](https://dl.static-php.dev/static-php-cli/minimal/)        | [5](https://dl.static-php.dev/static-php-cli/minimal/README.txt)           | Linux/macOS | 体积为 3MB 左右   |
| [spc-min](https://dl.static-php.dev/static-php-cli/windows/spc-min) | [5](https://dl.static-php.dev/static-php-cli/windows/spc-min/README.txt)   | Windows     | 体积为 3MB 左右   |
| [spc-max](https://dl.static-php.dev/static-php-cli/windows/spc-max) | [40+](https://dl.static-php.dev/static-php-cli/windows/spc-max/README.txt) | Windows     | 体积为 8.5MB 左右 |

> Linux 和 Windows 默认启用了 UPX 压缩，可减小 30~50% 的 PHP 二进制体积。
> macOS 当前不支持 UPX，所以上述预编译的 macOS 版本体积可能较大。

## 使用 static-php-cli 构建 PHP

### 编译环境需求

- PHP >= 8.1（这是 spc 自身需要的版本，不是支持的构建版本）
- 扩展：`mbstring,tokenizer,phar`
- 系统安装了 `curl` 和 `git`

是的，本项目采用 PHP 编写，编译前需要一个 PHP 环境，比较滑稽。
但本项目默认可通过自身构建的 micro 和 static-php 二进制运行，其他只需要包含上面提到的扩展和 PHP 版本大于等于 8.1 即可。

下面是架构支持情况，:octocat: 代表支持 GitHub Action 构建，:computer: 代表支持本地构建，空 代表暂不支持。

|         | x86_64               | aarch64              |
|---------|----------------------|----------------------|
| macOS   | :octocat: :computer: | :octocat: :computer: |
| Linux   | :octocat: :computer: | :octocat: :computer: |
| Windows | :octocat: :computer: |                      |
| FreeBSD | :computer:           | :computer:           |

当前支持编译的 PHP 版本：

> :warning: 支持，但 static-php-cli 作者可能不再提供补丁修复
> 
> :heavy_check_mark: 支持
> 
> :x: 不支持

| PHP Version | Status             | Comment                                      |
|-------------|--------------------|----------------------------------------------|
| 7.2         | :x:                |                                              |
| 7.3         | :x:                | phpmicro 和许多扩展不支持 7.3、7.4 版本                 |
| 7.4         | :x:                | phpmicro 和许多扩展不支持 7.3、7.4 版本                 |
| 8.0         | :warning:          | PHP 官方已停止 8.0 的维护，我们不再处理 8.0 相关的 backport 支持 |
| 8.1         | :heavy_check_mark: | PHP 官方仅对 8.1 提供安全更新                          |
| 8.2         | :heavy_check_mark: |                                              |
| 8.3         | :heavy_check_mark: |                                              |
| 8.4         | :heavy_check_mark: |                                              |

> 这个表格的支持状态是 static-php-cli 对构建对应版本的支持情况，不是 PHP 官方对该版本的支持情况。

### 支持的扩展

请先根据下方扩展列表选择你要编译的扩展。

- [扩展支持列表](https://static-php.dev/zh/guide/extensions.html)
- [编译命令生成器](https://static-php.dev/zh/guide/cli-generator.html)

> 如果这里没有你需要的扩展，可以提交 Issue。

### 在线构建（使用 GitHub Actions）

使用 GitHub Action 可以方便地构建一个静态编译的 PHP，同时可以自行定义要编译的扩展。

1. Fork 本项目。
2. 进入项目的 Actions，选择 CI。
3. 选择 `Run workflow`，填入你要编译的 PHP 版本、目标类型、扩展列表。（扩展列表使用英文逗号分割，例如 `bcmath,curl,mbstring`）
4. 等待大约一段时间后，进入对应的任务中，获取 `Artifacts`。

如果你选择了 `debug`，则会在构建时输出所有日志，包括编译的日志，以供排查错误。

### 本地构建（使用 spc 二进制，推荐）

该项目提供了 static-php-cli 的二进制文件：`spc`。
您可以使用 `spc` 二进制文件，无需安装任何运行时（用起来就像 golang 程序）。
目前，`spc` 二进制文件提供的平台有 Linux 和 macOS。

使用以下命令从自托管服务器下载：

```bash
# Download from self-hosted nightly builds (sync with main branch)
# For Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# For Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64
# Windows (x86_64, win10 build 17063 or later)
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe

# Add execute perm (Linux and macOS only)
chmod +x ./spc

# Run (Linux and macOS)
./spc --version
# Run (Windows powershell)
.\spc.exe --version
```

自托管 `spc` 由 GitHub Actions 构建，你也可以从 Actions 直接下载：[此处](https://github.com/crazywhalecc/static-php-cli/actions/workflows/release-build.yml)。

### 本地构建（使用 git 源码）

如果你需要修改 static-php-cli 源码，或者使用 spc 二进制构建有问题，你可以使用 git 源码下载 static-php-cli。

```bash
# clone 仓库即可
git clone https://github.com/crazywhalecc/static-php-cli.git
```

如果您的系统上尚未安装 php，我们建议你使用内置的 setup-runtime 自动安装 PHP 和 Composer。

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

### 开始构建 PHP

下面是使用 static-php-cli 的基础用法：

> 如果你使用的是打包好的 `spc` 二进制，你需要将下列命令的 `./bin/spc` 替换为 `./spc`。

```bash
# 检查环境依赖，并根据尝试自动安装缺失的编译工具
./bin/spc doctor --auto-fix

# 拉取所有依赖库
./bin/spc download --all
# 只拉取编译指定扩展需要的所有依赖（推荐）
./bin/spc download --for-extensions="openssl,pcntl,mbstring,pdo_sqlite"
# 下载依赖时，优先下载有预编译的库（节省编译依赖的时间）
./bin/spc download --for-extensions="openssl,curl,mbstring,mbregex" --prefer-pre-built
# 下载编译不同版本的 PHP (--with-php=x.y 或 --with-php=x.y.z，推荐 8.1 ~ 8.3)
./bin/spc download --for-extensions="openssl,curl,mbstring" --with-php=8.1

# 构建包含 bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl 扩展的 php-cli 和 micro.sfx
./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl" --build-cli --build-micro
# 编译线程安全版本 (--enable-zts)
./bin/spc build "curl,phar" --enable-zts --build-cli
# 编译后使用 UPX 减小可执行文件体积 (仅 Linux、Windows 可用) (至少压缩至原来的 30~50%)
./bin/spc build "curl,phar" --enable-zts --build-cli --with-upx-pack
```

其中，目前支持构建 cli，micro，fpm 和 embed，使用以下参数的一个或多个来指定编译的 SAPI：

- `--build-cli`：构建 cli 二进制
- `--build-micro`：构建 phpmicro 自执行二进制
- `--build-fpm`：构建 fpm
- `--build-embed`：构建 embed（libphp）
- `--build-all`：构建所有

如果出现了任何错误，可以使用 `--debug` 参数来展示完整的输出日志，以供排查错误：

```bash
./bin/spc build "openssl,pcntl,mbstring" --debug --build-all
./bin/spc download --all --debug
```

## 不同 SAPI 的使用

### 使用 cli

> php-cli 是一个静态的二进制文件，类似 Go、Rust 语言编译后的单个可移植的二进制文件。

采用参数 `--build-cli` 或`--build-all` 参数时，最后编译结果会输出一个 `./php` 的二进制文件，此文件可分发、可直接使用。
该文件编译后会存放在 `buildroot/bin/` 目录中，名称为 `php`，拷贝出来即可。

```bash
cd buildroot/bin/
./php -v                # 检查版本
./php -m                # 检查编译的扩展
./php your_code.php     # 运行代码
./php your_project.phar # 运行打包为 phar 单文件的项目
```

### 使用 micro

> phpmicro 是一个提供自执行二进制 PHP 的项目，本项目依赖 phpmicro 进行编译自执行二进制。详见 [dixyes/phpmicro](https://github.com/dixyes/phpmicro)。

采用项目参数 `--build-micro` 或 `--build-all` 时，最后编译结果会输出一个 `./micro.sfx` 的文件，此文件需要配合你的 PHP 源码使用。
该文件编译后会存放在 `buildroot/bin/` 目录中，拷贝出来即可。

使用时应准备好你的项目源码文件，可以是单个 PHP 文件，也可以是 Phar 文件。

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app
```

如果打包 PHAR 文件，仅需把 code.php 更换为 phar 文件路径即可。
你可以使用 [box-project/box](https://github.com/box-project/box) 将你的 CLI 项目打包为 Phar，
然后将它与 phpmicro 结合，生成独立可执行的二进制文件。

```bash
# 使用 static-php-cli 生成的 micro.sfx 结合，也可以直接使用 cat 命令结合它们
bin/spc micro:combine my-app.phar
cat buildroot/bin/micro.sfx my-app.phar > my-app && chmod +x my-app

# 使用 micro:combine 结合可以将 INI 选项注入到二进制中
bin/spc micro:combine my-app.phar -I "memory_limit=4G" -I "disable_functions=system" --output my-app-2
```

> 有些情况下的 phar 文件或 PHP 项目可能无法在 micro 环境下运行。

### 使用 fpm

采用项目参数 `--build-fpm` 或 `--build-all` 时，最后编译结果会输出一个 `./php-fpm` 的文件。
该文件存放在 `buildroot/bin/` 目录，拷贝出来即可使用。

在正常的 Linux 发行版和 macOS 系统中，安装 php-fpm 后包管理会自动生成默认的 fpm 配置文件。
因为 php-fpm 必须指定配置文件才可启动，本项目编译的 php-fpm 不会带任何配置文件，所以需自行编写 `php-fpm.conf` 和 `pool.conf` 配置文件。

指定 `php-fpm.conf` 可以使用命令参数 `-y`，例如：`./php-fpm -y php-fpm.conf`。

### 使用 embed

采用项目参数 `--build-embed` 或 `--build-all` 时，最后编译结果会输出一个 `libphp.a`、`php-config` 以及一系列头文件，存放在 `buildroot/`，你可以在你的其他代码中引入它们。

如果你知道 [embed SAPI](https://github.com/php/php-src/tree/master/sapi/embed)，你应该知道如何使用它。对于有可能编译用到引入其他库的问题，你可以使用 `buildroot/bin/php-config` 来获取编译时的配置。

另外，有关如何使用此功能的高级示例，请查看[如何使用它构建 FrankenPHP 的静态版本](https://github.com/dunglas/frankenphp/blob/main/docs/static.md)。

## 贡献

如果缺少你需要的扩展，可发起 Issue。如果你对本项目较熟悉，也欢迎为本项目发起 Pull Request。

另外，添加新扩展的贡献方式，可以参考下方 `进阶`。

如果你想贡献文档内容，请直接修改 `docs/` 目录。

## 赞助本项目

你可以在 [我的个人赞助页](https://github.com/crazywhalecc/crazywhalecc/blob/master/FUNDING.md) 支持我和我的项目。你捐赠的一部分将会被用于维护 **static-php.dev** 服务器。

## 开源协议

本项目采用 MIT License 许可开源，下面是类似的项目：

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)

该项目使用了 [dixyes/lwmbs](https://github.com/dixyes/lwmbs) 中的一些代码，例如 Windows 静态构建目标和 libiconv 库支持。
lwmbs 使用 [Mulan PSL 2](http://license.coscl.org.cn/MulanPSL2) 许可进行分发。对应文件有关于作者和许可的特殊说明，除此之外，均使用 MIT 授权许可。

因本项目的特殊性，使用项目编译过程中会使用很多其他开源项目，例如 curl、protobuf 等，它们都有各自的开源协议。
请在编译完成后，使用命令 `bin/spc dump-license` 导出项目使用项目的开源协议，并遵守对应项目的 LICENSE。
