# 本地构建（Linux、macOS、FreeBSD）

本章节为 Linux、macOS、FreeBSD 的构建过程，如果你要在 Windows 上构建，请到 [在 Windows 上构建](./build-on-windows)。

## 手动构建（使用 SPC 二进制）（推荐）

本项目提供了一个 static-php-cli 的二进制文件，你可以直接下载对应平台的二进制文件，然后使用它来构建静态的 PHP。目前 `spc` 二进制支持的平台有 Linux 和 macOS。

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

> 如果你使用的是打包好的 `spc` 二进制，你需要将下面所有命令中 `bin/spc` 开头替换为 `./spc`。

## 手动构建（使用源码）

如果使用 spc 二进制出现问题，或你有修改 static-php-cli 源码需求，请从源码下载 static-php-cli。

目前支持在 macOS、Linux 上构建，macOS 支持最新版操作系统和两种架构，Linux 支持 Debian、RHEL 及衍生发行版、Alpine Linux 等。

因为本项目本身采用 PHP 开发，所以在编译时也需要系统安装 PHP。本项目本身也提供了适用于本项目的静态二进制 php，可以根据实际情况自行选择使用。

### 下载本项目

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git --depth=1
cd static-php-cli

# 你需要先安装 PHP 环境后再运行 Composer 和本项目，安装方式可参考下面。
composer update
```

### 使用系统 PHP 环境

下面是系统安装 PHP、Composer 的一些示例命令。具体安装方式建议自行搜索或询问 AI 搜索引擎获取答案，这里不多赘述。

```bash
# [macOS], 需要先安装 Homebrew. See https://brew.sh/
# Remember change your composer executable path. For M1/M2 Chip mac, "/opt/homebrew/bin/", for Intel mac, "/usr/local/bin/". Or add it to your own path.
brew install php wget
wget https://getcomposer.org/download/latest-stable/composer.phar -O /path/to/your/bin/composer && chmod +x /path/to/your/bin/composer

# [Debian], you need to make sure your php version >= 8.1 and composer >= 2.0
sudo apt install php-cli composer php-tokenizer

# [Alpine]
apk add bash file wget xz php81 php81-common php81-pcntl php81-tokenizer php81-phar php81-posix php81-xml composer
```

::: tip
目前 Ubuntu 部分版本的 apt 安装的 php 版本较旧，故不提供安装命令。如有需要，建议先添加 ppa 等软件源后，安装最新版的 PHP 以及 tokenizer、xml、phar 扩展。

较老版本的 Debian 默认安装的可能为旧版本（<= 7.4）版本的 PHP，建议先升级 Debian。
:::

### 使用 Docker 环境

如果你不愿意在系统安装 PHP 和 Composer 运行环境，可以使用内置的 Docker 环境构建脚本。

```bash
# 直接使用，将所有使用的命令中 `bin/spc` 替换为 `bin/spc-alpine-docker` 即可
bin/spc-alpine-docker
```

首次执行命令会使用 `docker build` 构建一个 Docker 镜像，默认构建的 Docker 镜像为 `x86_64` 架构，镜像名称为 `cwcc-spc-x86_64`。

如果你想在 `x86_64` 环境下构建 `aarch64` 的 static-php-cli，可以使用 qemu 模拟 arm 镜像运行 Docker，但速度会非常慢。使用参数：`SPC_USE_ARCH=aarch64 bin/spc-alpine-docker`。

如果运行后提示需要 sudo 才能运行，执行一次以下命令可授予 static-php-cli 执行 sudo 的权限：

```bash
export SPC_USE_SUDO=yes
```

### 使用预编译静态 PHP 二进制

如果你不想使用 Docker、在系统内安装 PHP，可以直接下载本项目自身编译好的 php 二进制 cli 程序。使用流程如下：

使用命令部署环境，此脚本会从 [自托管的服务器](https://dl.static-php.dev/static-php-cli/) 下载一个当前操作系统的 php-cli 包，
并从 [getcomposer](https://getcomposer.org/download/latest-stable/composer.phar) 或 [Aliyun（镜像）](https://mirrors.aliyun.com/composer/composer.phar) 下载 Composer。

::: tip 
使用预编译静态 PHP 二进制目前仅支持 Linux 和 macOS。FreeBSD 环境因为缺少自动化构建环境，所以暂不支持。
:::

```bash
bin/setup-runtime

# 对于中国大陆地区等网络环境特殊的用户，可使用镜像站加快下载速度
bin/setup-runtime --mirror china
```

此脚本总共会下载两个文件：`bin/php` 和 `bin/composer`，下载完成后，有两种使用方式：

1. 将 `bin/` 目录添加到 PATH 路径中：`export PATH="/path/to/your/static-php-cli/bin:$PATH"`，添加路径后，相当于系统安装了 PHP，可直接使用 `composer`、`php -v` 等命令，也可以直接使用 `bin/spc`。
2. 直接调用，比如执行 static-php-cli 命令：`bin/php bin/spc --help`，执行 Composer：`bin/php bin/composer update`。

## 命令 download - 下载依赖包

使用命令 `bin/spc download` 可以下载编译需要的源代码，包括 php-src 以及依赖的各种库的源码。

```bash
# 仅下载要编译的扩展及依赖库（使用扩展名，包含可选库）
bin/spc download --for-extensions=openssl,swoole,zip,pcntl,zstd

# 仅下载要编译的扩展及依赖库（使用扩展名，不包含可选库）
bin/spc download --for-extensions=openssl,swoole,zip,pcntl --without-suggestions

# 仅下载要编译的库（包括其依赖，使用库名，包含可选库，可以和 --for-extensions 组合使用）
bin/spc download --for-libs=liblz4,libevent --for-extensions=pcntl,rar,xml

# 仅下载要编译的库（包括其依赖，使用库名，不包含可选库）
bin/spc download --for-libs=liblz4,libevent --without-suggestions

# 下载资源时，忽略部分资源的缓存，强制下载（如切换特定 PHP 版本）
bin/spc download --for-extensions=curl,pcntl,xml --ignore-cache-sources=php-src --with-php=8.3.10

# 下载资源时，优先下载有预编译包的依赖库（减少编译依赖的时间）
bin/spc download --for-extensions="curl,pcntl,xml,mbstring" --prefer-pre-built

# 下载所有依赖包
bin/spc download --all

# 下载所有依赖包，并指定下载的 PHP 主版本，可选：8.1，8.2，8.3，8.4，也可以使用特定的版本，如 8.3.10。
bin/spc download --all --with-php=8.3

# 下载时显示下载进度条（curl）
bin/spc download --all --debug

# 删除旧的下载数据
bin/spc download --clean

# 仅下载指定的资源（使用资源名）
bin/spc download php-src,micro,zstd,ext-zstd

# 设置重试次数
bin/spc download --all --retry=2
```

如果你所在地区的网络不好，或者下载依赖包速度过于缓慢，可以从 GitHub Action 下载每周定时打包的 `download.zip`，并使用命令直接使用 zip 压缩包作为依赖。
依赖包可以从 [Action](https://github.com/static-php/static-php-cli-hosted/actions/workflows/download-cache.yml) 下载到本地。
进入 Action 并选择一个最新成功运行的 Workflow，下载 `download-files-x.y` 即可。

```bash
bin/spc download --from-zip=/path/to/your/download.zip
```

如果某个 source 始终无法下载，或者你需要下载一些特定版本的包，例如下载测试版 PHP、旧版本库等，可以使用参数 `-U` 或 `--custom-url` 重写下载链接，
让下载器强制使用你指定的链接下载此 source 的包。使用方法为 `{source-name}:{url}` 即可，可同时重写多个库的下载地址。在使用 `--for-extensions` 选项下载时同样可用。

```bash
# 例如：指定下载测试版的 PHP8.3
bin/spc download --all -U "php-src:https://downloads.php.net/~eric/php-8.3.0beta1.tar.gz"

# 指定下载旧版本的 curl 库
bin/spc download --all -U "curl:https://curl.se/download/curl-7.88.1.tar.gz"
```

如果你下载的资源不是链接，而是一个 Git 仓库，你可以使用 `-G` 或 `--custom-git` 重写下载链接，让下载器强制使用你指定的 Git 仓库下载此 source 的包。
使用方法为 `{source-name}:{branch}:{url}` 即可，可同时重写多个库的下载地址。在使用 `--for-extensions` 选项下载时同样可用。

```bash
# 例如：下载 master 分支的 php-src
bin/spc download --for-extensions=redis,phar -G "php-src:master:https://github.com/php/php-src.git"

# 从 swoole-src 仓库下载 master 分支的最新代码，而不是发行版
bin/spc download --for-extensions=swoole -G "swoole:master:https://github.com/swoole/swoole-src.git"
```

## 命令 doctor - 环境检查

如果你可以正常运行 `bin/spc` 但无法正常编译静态的 PHP 或依赖库，可以先运行 `bin/spc doctor` 检查系统自身是否缺少依赖。

```bash
# 快速检查
bin/spc doctor

# 快速检查，并在可以自动修复的时候修复（使用包管理安装依赖包，仅支持上述提到的操作系统及发行版）
bin/spc doctor --auto-fix
```

## 命令 build - 编译 PHP

使用 build 命令可以开始构建静态 php 二进制，在执行 `bin/spc build` 命令前，务必先使用 `download` 命令下载资源，建议使用 `doctor` 检查环境。

### 基本用法

你需要先到 [扩展列表](./extensions) 或 [命令生成器](./cli-generator) 选择你要加入的扩展，然后使用命令 `bin/spc build` 进行编译。你需要指定一个编译目标，从如下参数中选择：

- `--build-cli`: 构建一个 cli sapi（命令行界面，可在命令行执行 PHP 代码）
- `--build-fpm`: 构建一个 fpm sapi（php-fpm，用于和其他传统的 fpm 架构的软件如 nginx 配合使用）
- `--build-micro`: 构建一个 micro sapi（用于构建一个包含 PHP 代码的独立可执行二进制）
- `--build-embed`: 构建一个 embed sapi（用于嵌入到其他 C 语言程序中）
- `--build-all`: 构建以上所有 sapi

```bash
# 编译 PHP，附带 bcmath,curl,openssl,ftp,posix,pcntl 扩展，编译目标为 cli
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli

# 编译 PHP，附带 phar,curl,posix,pcntl,tokenizer 扩展，编译目标为 micro
bin/spc build phar,curl,posix,pcntl,tokenizer --build-micro
```

::: tip
如果你需要重复构建、调试，你可以删除 `buildroot/` 和 `source/` 两个目录，这样你可以从已下载的源码压缩包重新解压并构建：

```shell
# remove
rm -rf buildroot source
# build again
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
```
:::

::: tip
如果你想构建多个版本的 PHP，且不想每次都重复构建其他依赖库，可以使用 `switch-php-version` 在编译好一个版本后快速切换至另一个版本并编译：

```shell
# switch to 8.4
bin/spc switch-php-version 8.4
# build
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
# switch to 8.1
bin/spc switch-php-version 8.1
# build
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
```
:::

### 调试

如果你在编译过程中遇到了问题，或者想查看每个执行的 shell 命令，可以使用 `--debug` 开启 debug 模式，查看所有终端日志：

```bash
bin/spc build mysqlnd,pdo_mysql --build-all --debug
```

### 编译运行选项

在编译过程中，有些特殊情况需要对编译器、编译目录的内容进行干预，可以尝试使用以下命令：

- `--cc=XXX`: 指定 C 语言编译器的执行命令（Linux 默认 `musl-gcc` 或 `gcc`，macOS 默认 `clang`）
- `--cxx=XXX`: 指定 C++ 语言编译器的执行命令（Linux 默认 `g++`，macOS 默认 `clang++`）
- `--with-clean`: 编译 PHP 前先清理旧的 make 产生的文件
- `--enable-zts`: 让编译的 PHP 为线程安全版本（默认为 NTS 版本）
- `--no-strip`: 编译 PHP 库后不运行 `strip` 裁剪二进制文件缩小体积（不裁剪的 macOS 二进制文件可使用动态链接的第三方扩展）
- `--with-libs=XXX,YYY`: 编译 PHP 前先编译指定的依赖库，激活部分扩展的可选功能（例如 gd 库的 libavif 等）
- `--with-config-file-path=XXX`： 查找 `php.ini` 的路径（在 [这里](../faq/index.html#php-ini-的路径是什么) 查看默认路径）
- `--with-config-file-scan-dir=XXX`： 读取 `php.ini` 后扫描 `.ini` 文件的目录（在 [这里](../faq/index.html#php-ini-的路径是什么) 查看默认路径）
- `-I xxx=yyy`: 编译前将 INI 选项硬编译到 PHP 内（支持多个选项，别名是 `--with-hardcoded-ini`）
- `--with-micro-fake-cli`: 在编译 micro 时，让 micro 的 SAPI 伪装为 `cli`（用于兼容一些检查 `PHP_SAPI` 的程序）
- `--disable-opcache-jit`: 禁用 opcache jit（默认启用）
- `-P xxx.php`: 在 static-php-cli 编译过程中注入外部脚本（详见下方 **注入外部脚本**）
- `--without-micro-ext-test`: 在构建 micro.sfx 后，禁用测试不同扩展在 micro.sfx 的运行结果
- `--with-suggested-exts`: 编译时将 `ext-suggests` 也作为编译依赖加入
- `--with-suggested-libs`: 编译时将 `lib-suggests` 也作为编译依赖加入
- `--with-upx-pack`: 编译后使用 UPX 减小二进制文件体积（需先使用 `bin/spc install-pkg upx` 安装 upx）

硬编码 INI 选项适用于 cli、micro、embed。有关硬编码 INI 选项，下面是一个简单的例子，我们预设一个更大的 `memory_limit`，并且禁用 `system` 函数：

```bash
bin/spc build bcmath,pcntl,posix --build-all -I "memory_limit=4G" -I "disable_functions=system"
```

## 命令 micro:combine - 打包 micro 二进制

使用 `micro:combine` 命令可以将上面编译好的 `micro.sfx` 和你的代码（`.php` 或 `.phar` 文件）构建为一个可执行二进制。
你也可以使用该命令直接构建一个注入了 ini 配置的 micro 自执行二进制文件。

::: tip
注入 ini 配置指的是，在将 micro.sfx 和 PHP 源码结合前，在 micro.sfx 后追加一段特殊的结构用于保存 ini 配置项。

micro.sfx 可通过特殊的字节来标识 INI 文件头，通过 INI 文件头可以实现 micro 带 INI 启动。

此特性的原说明地址在 [phpmicro - Wiki](https://github.com/easysoft/phpmicro/wiki/INI-settings)，这个特性也有可能在未来发生变化。
:::

下面是常规用法，直接打包 php 源码到一个文件中：

```bash
# 在做打包流程前，你应该先使用 `build --build-micro` 编译好 micro.sfx
echo "<?php echo 'hello';" > a.php
bin/spc micro:combine a.php

# 使用
./my-app
```

你可以使用以下参数指定要输出的文件名，你也可以指定其他路径的 micro.sfx 进行打包。

```bash
# 指定输出文件名
bin/spc micro:combine a.php --output=custom-bin
# 使用绝对路径，也可以使用简化参数名
bin/spc micro:combine a.php -O /tmp/my-custom-app

# 指定其他位置的 micro.sfx 进行打包
bin/spc micro:combine a.app --with-micro=/path/to/your/micro.sfx
```

如果想注入 ini 配置项，可以使用下面的参数，从文件或命令行选项添加 ini 到可执行文件中。

```bash
# 使用命令行选项指定（-I 是 --with-ini-set 的简写）
bin/spc micro:combine a.php -I "a=b" -I "foo=bar"

# 使用 ini 文件指定（-N 是 --with-ini-file 的简写）
bin/spc micro:combine a.php -N /path/to/your/custom.ini
```

::: warning
注意，请不要直接使用 PHP 源码或系统安装的 PHP 中的 `php.ini` 文件，最好手动编写一个自己需要的参数配置文件，例如：

```ini
; custom.ini
curl.cainfo=/path/to/your/cafile.pem
memory_limit=1G
```

该命令的注入 ini 是通过在 micro.sfx 后追加一段特殊的结构来实现的，和编译时插入硬编码 INI 的功能不同。
:::

如果要打包 phar，只需要将 `a.php` 替换为打包好的 phar 文件即可。但要注意，phar 下的 micro.sfx 需要额外注意路径问题，见 [Developing - Phar 路径问题](../develop/structure#phar-应用目录问题)

## 命令 extract - 手动解压某个库

使用命令 `bin/spc extract` 可以解包和拷贝编译需要的源代码，包括 php-src 以及依赖的各种库的源码（需要自己指定要解包的库名）。

例如，我们在下载好资源后，想分布执行构建流程，手动解包和拷贝包到指定位置，可以使用命令。

```bash
# 解压 php-src 和 libxml2 的下载压缩包，解压的源码存放在 source 目录
bin/spc extract php-src,libxml2
```

## 调试命令 dev - 调试命令集合

调试命令指的是你在使用 static-php-cli 构建 PHP 或改造、增强 static-php-cli 项目本身的时候，可以辅助输出一些信息的命令集合。

- `dev:extensions`: 输出目前所有支持的扩展信息，或者输出指定的扩展信息
- `dev:php-version`: 输出当前编译的 PHP 版本（通过读取 `php_version.h` 实现）
- `dev:sort-config`: 对 `config/` 目录下的配置文件的列表按照字母表排序
- `dev:lib-ver <lib-name>`: 从依赖库的源码中读取版本（仅特定依赖库可用）
- `dev:ext-ver <ext-name>`: 从扩展的源码中读取对应版本（仅特定扩展可用）
- `dev:pack-lib <lib-name>`: 打包指定的依赖库（仅发布者可用）
- `dev:gen-ext-docs`: 生成扩展文档（仅发布者可用）

```bash
# 输出所有扩展
bin/spc dev:extensions

# 输出指定扩展的信息
bin/spc dev:extensions mongodb,curl,openssl

# 输出指定列，可选：lib-depends, lib-suggests, ext-depends, ext-suggests, unix-only, type
bin/spc dev:extensions --columns=lib-depends,type,ext-depends

# 输出当前编译的 PHP 版本（需要先将下载好的 PHP 源码解压到 source 目录，你可以使用 `bin/spc extract php-src` 单独解压缩源码）
bin/spc dev:php-version

# 排序配置文件 ext.json（也可以排序 lib、source）
bin/spc dev:sort-config ext
```

## 命令 install-pkg - 下载二进制包

使用命令 `bin/spc install-pkg` 可以下载一些预编译或闭源的工具，并将其安装到 `pkgroot` 目录中。

在 `bin/spc doctor` 自动修复 Windows 环境时会下载 nasm、perl 等工具，使用的也是 `install-pkg` 的安装过程。

下面是安装工具的示例：

- 下载安装 UPX（仅限 Linux 和 Windows）: `bin/spc install-pkg upx`

## 命令 del-download - 删除已下载的资源

一些情况下，你需要删除单个或多个指定的下载源文件，并重新下载他们，例如切换 PHP 版本，`2.1.0-beta.4` 版本后提供了 `bin/spc del-download` 命令，可以删除指定源文件。

删除已下载的源文件包含预编译的包以及源代码，名称是 `source.json` 或 `pkg.json` 中的键名。下面是一些例子：

- 删除 PHP 8.2 源码并切换下载为 8.3 版本: `bin/spc del-download php-src && bin/spc download php-src --with-php=8.3`
- 删除 redis 扩展的下载文件: `bin/spc del-download redis`
- 删除下载好的 musl-toolchain x86_64: `bin/spc del-download musl-toolchain-x86_64-linux`

## 注入外部脚本

注入外部脚本指的是在 static-php-cli 编译过程中插入一个或多个脚本，用于更灵活地支持不同环境下的参数修改、源代码补丁。

一般情况下，该功能主要解决使用 `spc` 二进制进行编译时无法通过修改 static-php-cli 代码来实现修改补丁的功能。
还有一种情况：你的项目直接依赖了 `crazywhalecc/static-php-cli` 仓库并同步，但因为项目特性需要做出一些专有的修改，而这些特性并不适合合并到主分支。

鉴于以上情况，在 2.0.1 正式版本中，static-php-cli 加入了多个事件的触发点，你可以通过编写外部的 `xx.php` 脚本，并通过命令行参数 `-P` 传入并执行。

在编写注入外部脚本时，你一定会用到的方法是 `builder()` 和 `patch_point()`。其中，`patch_point()` 获取的是当前正在执行的事件名称，`builder()` 获取的是 BuilderBase 对象。

因为传入的注入点不区分事件，所以你必须将你要执行的代码写在 `if(patch_point() === 'your_event_name')` 中，否则会重复在其他事件中执行。

下面是支持的 patch_point 事件名称及对应位置：

| 事件名称                         | 事件描述                                                      |
|------------------------------|-----------------------------------------------------------|
| before-libs-extract          | 在编译的依赖库解压前触发                                              |
| after-libs-extract           | 在编译的依赖库解压后触发                                              |
| before-php-extract           | 在 PHP 源码解压前触发                                             |
| after-php-extract            | 在 PHP 源码解压后触发                                             |
| before-micro-extract         | 在 phpmicro 解压前触发                                          |
| after-micro-extract          | 在 phpmicro 解压后触发                                          |
| before-exts-extract          | 在要编译的扩展解压到 PHP 源码目录前触发                                    |
| after-exts-extract           | 在要编译的扩展解压到 PHP 源码目录后触发                                    |
| before-library[*name*]-build | 在名称为 `name` 的库编译前触发（如 `before-library[postgresql]-build`） |
| after-library[*name*]-build  | 在名称为 `name` 的库编译后触发                                       |
| before-php-buildconf         | 在编译 PHP 命令 `./buildconf` 前触发                              |
| before-php-configure         | 在编译 PHP 命令 `./configure` 前触发                              |
| before-php-make              | 在编译 PHP 命令 `make` 前触发                                     |
| before-sanity-check          | 在编译 PHP 后，运行扩展检查前触发                                       |

下面是一个简单的临时修改 PHP 源码的例子，开启 CLI 下在当前工作目录查找 `php.ini` 配置的功能：

```php
// a.php
<?php
if (patch_point() === 'before-php-buildconf') {
    // replace php source code
    \SPC\store\FileSystem::replaceFileStr(
        SOURCE_PATH . '/php-src/sapi/cli/php_cli.c',
        'sapi_module->php_ini_ignore_cwd = 1;',
        'sapi_module->php_ini_ignore_cwd = 0;'
    );
}
```

```bash
bin/spc build mbstring --build-cli -P a.php
echo 'memory_limit=8G' > ./php.ini
```

```
$ buildroot/bin/php -i | grep Loaded
Loaded Configuration File => /Users/jerry/project/git-project/static-php-cli/php.ini

$ buildroot/bin/php -i | grep memory
memory_limit => 8G => 8G
```

对于 static-php-cli 支持的对象、方法及接口，可以阅读源码，大部分的方法和对象都有相应的注释。

一般使用 `-P` 功能常用的对象及函数有：

- `SPC\store\FileSystem`: 文件管理类
  - `::replaceFileStr(string $filename, string $search, $replace)`: 替换文件字符串内容
  - `::replaceFileStr(string $filename, string $pattern, $replace)`: 正则替换文件内容
  - `::replaceFileUser(string $filename, $callback)`: 用户自定义函数替换文件内容
  - `::copyDir(string $from, string $to)`: 递归拷贝某个目录到另一个位置
  - `::convertPath(string $path)`: 转换路径的分隔符为当前系统分隔符
  - `::scanDirFiles(string $dir, bool $recursive = true, bool|string $relative = false, bool $include_dir = false)`: 遍历目录文件
- `SPC\builder\BuilderBase`: 构建对象
  - `->getPatchPoint()`: 获取当前的注入点名称
  - `->getOption(string $key, $default = null)`: 获取命令行和编译时的选项
  - `->getPHPVersionID()`: 获取当前编译的 PHP 版本 ID
  - `->getPHPVersion()`: 获取当前编译的 PHP 版本号
  - `->setOption(string $key, $value)`: 设定选项
  - `->setOptionIfNotExists(string $key, $value)`: 如果选项不存在则设定选项

::: tip
static-php-cli 开放的方法非常多，文档中无法一一列举，但只要是 `public function` 并且不被标注为 `@internal`，均可调用。
:::

## 多次构建

如果你在本地要多次构建，以下方法可以为你节省下载资源、编译的时间。

- 仅切换 PHP 版本，不更换依赖库版本时，可以使用 `bin/spc switch-php-version` 快速切换 PHP 版本，然后重新运行同样的 `build` 命令。
- 如果你想重新构建一次，但不重新下载源码，可以先 `rm -rf buildroot source` 删除编译目录和源码目录，然后重新构建。
- 如果你想更新某个依赖的版本，可以使用 `bin/spc del-download <source-name>` 删除指定的源码，然后使用 `download <source-name>` 重新下载。
- 如果你想更新所有依赖的版本，可以使用 `bin/spc download --clean` 删除所有下载的源码，然后重新下载。

## embed 使用

如果你想将 static-php 嵌入到其他 C 语言程序中，可以使用 `--build-embed` 构建一个 embed 版本的 PHP。

```bash
bin/spc build {your extensions} --build-embed --debug
```

在通常的情况下，PHP embed 编译后会生成 `php-config`。对于 static-php，我们提供了 `spc-config`，用于获取编译时的参数。
另外，在使用 embed SAPI（libphp.a）时，你需要使用和编译 libphp 相同的编译器，否则会出现链接错误。

下面是 spc-config 的基本用法：

```bash
# output all flags and options
bin/spc spc-config curl,zlib,phar,openssl

# output libs
bin/spc spc-config curl,zlib,phar,openssl --libs

# output includes
bin/spc spc-config curl,zlib,phar,openssl --includes
```

默认情况下，static-php 在不同系统使用的编译器分别是：

- macOS: `clang`
- Linux (Alpine Linux): `gcc`
- Linux (glibc based distros, x86_64): `/usr/local/musl/bin/x86_64-linux-musl-gcc`
- Linux (glibc based distros, aarch64): `/usr/local/musl/bin/aarch64-linux-musl-gcc`
- FreeBSD: `clang`

下面是一个使用 embed SAPI 的例子：

```c
// embed.c
#include <sapi/embed/php_embed.h>

int main(int argc,char **argv){

    PHP_EMBED_START_BLOCK(argc,argv)

    zend_file_handle file_handle;

    zend_stream_init_filename(&file_handle,"embed.php");

    if(php_execute_script(&file_handle) == FAILURE){
        php_printf("Failed to execute PHP script.\n");
    }

    PHP_EMBED_END_BLOCK()
    return 0;
}
```


```php
<?php 
// embed.php
echo "Hello world!\n";
```

```bash
# compile in debian/ubuntu x86_64
/usr/local/musl/bin/x86_64-linux-musl-gcc embed.c $(bin/spc spc-config bcmath,zlib) -static -o embed
# compile in macOS/FreeBSD
clang embed.c $(bin/spc spc-config bcmath,zlib) -o embed

./embed
# out: Hello world!
```
