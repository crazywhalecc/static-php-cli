# static-php-cli

Compile A Statically Linked PHP With Swoole and other Extensions. 

If you are using English, see [English README](README-en.md).

编译纯静态的 PHP Binary 二进制文件，带有各种扩展，让 PHP-cli 应用变得更便携！（cli SAPI）

<img width="600" alt="截屏2023-05-02 15 53 13" src="https://user-images.githubusercontent.com/20330940/235610282-23e58d68-bd35-4092-8465-171cff2d5ba8.png">

同时可以使用 micro 二进制文件，将 PHP 源码和 PHP 二进制构建为一个文件分发！（由 [dixyes/phpmicro](https://github.com/dixyes/phpmicro) 提供支持）（micro SAPI）

<img width="600" alt="截屏2023-05-02 15 52 33" src="https://user-images.githubusercontent.com/20330940/235610318-2ef4e3f1-278b-4ca4-99f4-b38120efc395.png">

[![Version](https://img.shields.io/badge/Version-2.0--rc1-pink.svg?style=flat-square)]()
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)]()
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-linux-x86_64.yml?branch=refactor&label=Linux%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)
[![](https://img.shields.io/github/actions/workflow/status/crazywhalecc/static-php-cli/build-macos-x86_64.yml?branch=refactor&label=macOS%20Build&style=flat-square)](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build.yml)
[![](https://img.shields.io/badge/Extension%20Counter-50+-yellow.svg?style=flat-square)]()
[![](https://img.shields.io/github/search/crazywhalecc/static-php-cli/TODO?label=TODO%20Counter&style=flat-square)]()

## 编译环境需求

是的，本项目采用 PHP 编写，编译前需要一个 PHP 环境，比较滑稽。
但本项目默认可通过自身构建的 micro 和 static-php 二进制运行，其他只需要包含 tokenizer 扩展和 PHP 版本大于等于 8.0 即可。

下面是架构支持情况，`CI` 代表支持 GitHub Action 构建，`Local` 代表支持本地构建，空 代表暂不支持。

|         | x86_64    | aarch64   |
|---------|-----------|-----------|
| macOS   | CI, Local | Local     |
| Linux   | CI, Local | CI, Local |
| Windows |           |           |

> macOS-arm64 因 GitHub 暂未提供 arm runner，如果要构建 arm 二进制，可以使用手动构建。

目前支持编译的 PHP 版本为：`7.4`，`8.0`，`8.1`，`8.2`。

## 使用

请先根据下方扩展列表选择你要编译的扩展。

### 自托管直接下载

如果你不想自行编译，可以从本项目现有的 Action 下载 Artifact，也可以从自托管的服务器下载：[进入](https://dl.zhamao.xin/static-php-cli/)

> 自托管的服务器默认包含的扩展有：`bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip`

### 支持的扩展情况

[扩展支持列表](/ext-support.md)

> 如果这里没有你需要的扩展，可以提交 Issue。

### 使用 Actions 构建

使用 GitHub Action 可以方便地构建一个静态编译的 PHP 和 phpmicro，同时可以自行定义要编译的扩展。

1. Fork 本项目。
2. 进入项目的 Actions，选择 CI。
3. 选择 `Run workflow`，填入你要编译的 PHP 版本、目标类型、扩展列表。（扩展列表使用英文逗号分割，例如 `bcmath,curl,mbstring`）
4. 等待大约一段时间后，进入对应的任务中，获取 `Artifacts`。

如果你选择了 `debug`，则会在构建时输出所有日志，包括编译的日志，以供排查错误。

### 手动构建

先克隆本项目：

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
```

如果你本机没有安装 PHP，你需要先使用包管理（例如 brew、apt、yum、apk 等）安装 php。

你也可以通过 `bin/setup-runtime` 命令下载静态编译好的 php-cli 和 Composer。下载的 php 和 Composer 将保存为 `bin/php` 和 `bin/composer`。

```bash
cd static-php-cli
chmod +x bin/setup-runtime
./bin/setup-runtime

# 使用独立的 php 运行 static-php-cli
./bin/php bin/spc

# 使用 composer
./bin/php bin/composer
```

下面是使用 static-php-cli 编译静态 php 和 micro 的基础用法：

```bash
# 克隆本项目
cd static-php-cli
composer update
chmod +x bin/spc
# 检查环境依赖，并根据提示的命令安装缺失的编译工具（目前仅支持 macOS，Linux 后续会支持）
./bin/spc doctor
# 拉取所有依赖库
./bin/spc fetch --all
# 构建包含 bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl 扩展的 php-cli 和 micro.sfx
./bin/spc build "bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl" --build-cli --build-micro
```

你也可以使用参数 `--with-php=x.y` 来指定下载的 PHP 版本，目前支持 7.4 ~ 8.2：

```bash
# 优先考虑使用 >= 8.0 的 PHP 版本
./bin/spc fetch --with-php=8.2 --all
```

其中，目前支持构建 cli，micro，fpm 三种静态二进制，使用以下参数的一个或多个来指定编译的 SAPI：

- `--build-cli`：构建 cli 二进制
- `--build-micro`：构建 phpmicro 自执行二进制
- `--build-fpm`：构建 fpm
- `--build-all`：构建所有

如果出现了任何错误，可以使用 `--debug` 参数来展示完整的输出日志，以供排查错误：

```bash
./bin/spc build openssl,pcntl,mbstring --debug --build-all
./bin/spc fetch --all --debug
```

此外，默认编译的 PHP 为 NTS 版本。如需编译线程安全版本（ZTS），只需添加参数 `--enable-zts` 即可。

```bash
./bin/spc build openssl,pcntl --build-all --enable-zts
```

同时，你也可以使用参数 `--no-strip` 来关闭裁剪，关闭裁剪后可以使用 gdb 等工具调试，但这样会让静态二进制体积变大。

### 使用 php-cli

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

### 使用 micro.sfx

> phpmicro 是一个提供自执行二进制 PHP 的项目，本项目依赖 phpmicro 进行编译自执行二进制。详见 [dixyes/phpmicro](https://github.com/dixyes/phpmicro)。

采用项目参数 `--build-micro` 或 `--build-all` 时，最后编译结果会输出一个 `./micro.sfx` 的文件，此文件需要配合你的 PHP 源码使用。
该文件编译后会存放在 `buildroot/bin/` 目录中，拷贝出来即可。

使用时应准备好你的项目源码文件，可以是单个 PHP 文件，也可以是 Phar 文件。

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app

# 如果打包 PHAR 文件，仅需把 code.php 更换为 phar 文件路径即可
```

> 有些情况下的 phar 文件可能无法在 micro 环境下运行。

### 使用 php-fpm

采用项目参数 `--build-fpm` 或 `--build-all` 时，最后编译结果会输出一个 `./php-fpm` 的文件。
该文件存放在 `buildroot/bin/` 目录，拷贝出来即可使用。

在正常的 Linux 发行版和 macOS 系统中，安装 php-fpm 后包管理会自动生成默认的 fpm 配置文件。
因为 php-fpm 必须指定配置文件才可启动，本项目编译的 php-fpm 不会带任何配置文件，所以需自行编写 `php-fpm.conf` 和 `pool.conf` 配置文件。

指定 `php-fpm.conf` 可以使用命令参数 `-y`，例如：`./php-fpm -y php-fpm.conf`。

## 项目支持情况

- [X] 基础结构编写（采用 `symfony/console`）
- [X] 错误处理
- [X] macOS 支持
- [ ] Windows 支持
- [X] Linux 支持
- [X] PHP 7.4 支持
- [X] fpm 支持

更多功能和特性正在陆续支持中，详见：https://github.com/crazywhalecc/static-php-cli/issues/32

## 贡献

目前支持的扩展较少，如果缺少你需要的扩展，可发起 Issue。如果你对本项目较熟悉，也欢迎为本项目发起 Pull Request。

贡献基本原则如下：

- 项目采用了 php-cs-fixer、phpstan 作为代码规范工具，贡献前请对更新的代码执行 `composer analyze` 和 `composer cs-fix`。
- 涉及到其他开源库的部分应提供对应库的协议，同时对配置文件在修改后采用命令 `sort-config` 排序。有关排序的命令，见文档。
- 应遵循命名规范，例如扩展名称应采取 PHP 内注册的扩展名本身，外部库名应遵循项目本身的名称，内部逻辑的函数、类名、变量等应遵循驼峰、下划线等格式，禁止同一模块混用。
- 涉及编译外部库的命令和 Patch 时应注意兼容不同操作系统。

另外，添加新扩展的贡献方式，可以参考下方 `进阶`。

## 赞助本项目

你可以在 [我的个人赞助页](https://github.com/crazywhalecc/crazywhalecc/blob/master/FUNDING.md) 支持我和我的项目。

## 开源协议

本项目依据旧版本惯例采用 MIT License 开源，自身的部分代码引用或修改自以下项目：

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)（木兰宽松许可证）
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)（Apache 2.0 LICENSE、SWOOLE-CLI LICENSE）

因本项目的特殊性，使用项目编译过程中会使用很多其他开源项目，例如 curl、protobuf 等，它们都有各自的开源协议。
请在编译完成后，使用命令 `bin/spc dump-license` 导出项目使用项目的开源协议，并遵守对应项目的 LICENSE。

## 进阶

本项目重构分支为模块化编写。

TODO：这部分将在基础功能完成后编写完成。
