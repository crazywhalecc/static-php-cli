---
outline: 'deep'
---

# 命令行参考

::: tip
如果你采用的是 spc 二进制方式安装，请将本章节中的所有 `spc` 替换为 `./spc` 或 `.\spc.exe`。

如果你采用的是源码安装，请将 `spc` 替换为 `bin/spc`。
:::

## download

下载构建所需的源码包和预编译二进制。

```bash
spc download [artifacts] [options]
```

`artifacts`（可选）：指定要下载的制品名称，逗号分隔（如 `"php-src,openssl,curl"`）。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--for-extensions=<list>` | `-e` | 按扩展名下载其所需的制品 |
| `--for-libs=<list>` | `-l` | 按库名下载其所需的制品 |
| `--for-packages=<list>` | | 按包名下载其所需的制品 |
| `--without-suggests` | | 使用 `--for-extensions` 时跳过建议包 |
| `--clean` | | 下载前删除旧的下载缓存 |
| `--with-php=<ver>` | | PHP 版本，格式为 `major.minor`（默认 `8.4`）|
| `--prefer-binary` | `-p` | 优先使用预编译二进制 |
| `--prefer-source` | | 优先使用源码包 |
| `--source-only` | | 仅下载源码制品 |
| `--binary-only` | | 仅下载二进制制品 |
| `--parallel=<n>` | `-P` | 并行下载数（默认 `1`）|
| `--retry=<n>` | `-R` | 失败重试次数（默认 `0`）|
| `--ignore-cache=<list>` | | 强制重新下载指定制品 |
| `--no-alt` | | 不使用镜像站 |
| `--no-shallow-clone` | | 不使用浅层克隆 |
| `--custom-url=<src:url>` | `-U` | 覆盖指定源的下载地址 |
| `--custom-git=<src:branch:url>` | `-G` | 覆盖为自定义 git 仓库 |
| `--custom-local=<src:path>` | `-L` | 使用本地路径作为制品来源 |

### 示例

```bash
# 按扩展名下载（推荐）
spc download --for-extensions="bcmath,openssl,curl" --with-php=8.4

# 下载指定制品
spc download "php-src,openssl"

# 增加并行数和重试次数
spc download --for-extensions="bcmath,openssl,curl" --parallel 8 --retry=3

# 优先使用预编译二进制
spc download --for-extensions="bcmath,openssl,curl" --prefer-binary

# 强制重新下载 PHP 源码（如切换版本）
spc download --for-extensions="bcmath,curl" --ignore-cache="php-src" --with-php=8.3

# 覆盖下载地址
spc download --for-extensions="bcmath" --custom-url "php-src:https://downloads.php.net/~user/php-8.5.0alpha1.tar.xz"
```

## build:php {#build-php}

从源码编译 PHP 及扩展。别名：`build`。

```bash
spc build:php <extensions> [options]
```

`extensions`（必填）：要静态编译的扩展名列表，逗号分隔（如 `"bcmath,openssl,curl"`）。

`build:php` 上也可使用所有 `download` 选项，只需加上 `--dl-` 前缀（如 `--dl-with-php=8.3`、`--dl-parallel=4`），这些参数将传递给构建前自动运行的下载器。

### SAPI 选择 {#sapi-selection}

以下选项仅适用于 `build:php` 组合目标。如需单独构建某个 SAPI，请使用对应的专用命令（如 `spc build:php-cli`）。

| 选项 | 说明 |
|---|---|
| `--build-cli` | 构建 `cli` SAPI（`php` / `php.exe`）|
| `--build-fpm` | 构建 `php-fpm`（仅 Linux 和 macOS）|
| `--build-cgi` | 构建 `php-cgi` |
| `--build-micro` | 构建 `micro.sfx` |
| `--build-embed` | 构建 embed 静态库（`libphp.a` / `php8embed.lib`）|
| `--build-frankenphp` | 构建 FrankenPHP 二进制 |

### 通用构建选项 {#common-build-options}

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--no-strip` | | 保留调试符号，不精简二进制 |
| `--with-upx-pack` | | 用 UPX 压缩产物（需先 `spc install-pkg upx`；仅 Linux 和 Windows）|
| `--disable-opcache-jit` | | 禁用 OPcache JIT |
| `--with-config-file-path=<path>` | | PHP 查找 `php.ini` 的目录（默认：`/usr/local/etc/php`）|
| `--with-config-file-scan-dir=<path>` | | PHP 扫描追加 `.ini` 文件的目录（默认：`/usr/local/etc/php/conf.d`）|
| `--with-hardcoded-ini=<k=v>` | `-I` | 编译时将 INI 配置硬编码进二进制（可重复使用）|
| `--enable-zts` | | 启用线程安全（ZTS）模式 |
| `--no-smoke-test` | | 跳过构建后的冒烟测试 |
| `--with-suggests` | `-L` / `-E` | 同时解析并安装建议包 |
| `--with-packages=<list>` | | 额外安装的包 |
| `--no-download` | | 跳过下载步骤（使用已有缓存）|
| `--with-added-patch=<file>` | `-P` | 注入外部 PHP 补丁脚本（可重复使用）|
| `--build-shared=<list>` | `-D` | 指定编译为共享 `.so` / `.dll` 的扩展 |

### micro 专用选项 {#micro-options}

| 选项 | 说明 |
|---|---|
| `--with-micro-fake-cli` | 让 `micro` 的 `PHP_SAPI` 报告为 `cli` 而非 `micro` |
| `--without-micro-ext-test` | 跳过构建后的 `micro.sfx` 扩展测试 |
| `--with-micro-logo=<path>` | 为 `micro.sfx` 嵌入自定义 `.ico` 图标（仅 Windows）|
| `--enable-micro-win32` | 将 `micro.sfx` 构建为 Win32 GUI 程序而非控制台程序（仅 Windows）|

### frankenphp 专用选项 {#frankenphp-options}

| 选项 | 说明 |
|---|---|
| `--enable-zts` | FrankenPHP 必须开启线程安全 |
| `--with-frankenphp-app=<path>` | 将指定目录嵌入到 FrankenPHP 二进制中 |

### embed 专用选项 {#embed-options}

| 选项 | 说明 |
|---|---|
| `--build-shared=<list>` | 将指定扩展编译为共享库（需要 embed SAPI）|

### 下载透传选项 {#download-options}

所有下载器选项均可加 `--dl-` 前缀使用：

| 选项 | 说明 |
|---|---|
| `--dl-with-php=<ver>` | 指定下载的 PHP 版本（默认 `8.4`）|
| `--dl-prefer-binary` | 优先使用预编译二进制依赖 |
| `--dl-parallel=<n>` | 并行下载数 |
| `--dl-retry=<n>` | 失败重试次数 |
| `--dl-custom-url=<src:url>` | 覆盖指定源的下载地址 |
| `--dl-custom-git=<src:branch:url>` | 覆盖为自定义 git 仓库 |

### 示例

```bash
# 构建 cli SAPI
spc build:php "bcmath,openssl,curl" --build-cli

# 同时构建 cli + micro
spc build:php "bcmath,phar,openssl,curl" --build-cli --build-micro

# 指定 PHP 版本
spc build:php "bcmath,openssl" --build-cli --dl-with-php=8.3

# 硬编码 INI 到二进制
spc build:php "bcmath,pcntl" --build-cli -I "memory_limit=4G" -I "disable_functions=system"

# 保留调试符号
spc build:php "bcmath,openssl" --build-cli --no-strip

# 构建 FrankenPHP（需开启 ZTS）
spc build:php "bcmath,openssl,curl" --build-frankenphp --enable-zts
```

## build:php-cli, build:php-fpm, build:php-micro, build:php-embed, build:php-cgi, build:frankenphp

专用单目标构建命令，接受与 `build:php` 相同的选项，但不需要 SAPI 选择标志（`--build-*`），目标已隐式确定。

```bash
spc build:php-cli "bcmath,openssl,curl"
spc build:php-micro "bcmath,phar,openssl"
spc build:php-fpm "bcmath,openssl,curl,pdo_mysql"
spc build:php-embed "bcmath,openssl"
spc build:frankenphp "bcmath,openssl,curl" --enable-zts
```

## craft

读取 `craft.yml` 并自动完成全流程构建。

```bash
spc craft [path/to/craft.yml]
```

未指定路径时，使用当前工作目录下的 `craft.yml`。配置格式参见 [craft.yml 配置](../develop/craft-yml)。

## doctor

检查当前环境是否满足编译要求。

```bash
spc doctor [--auto-fix[=never]]
```

| 选项 | 说明 |
|---|---|
| `--auto-fix` | 自动修复检测到的问题（使用系统包管理器）|
| `--auto-fix=never` | 仅报告问题，不尝试自动修复 |

## dev:shell

进入加载了 StaticPHP 构建环境的交互式 Shell（编译器 wrapper、`buildroot/`、`pkgroot/` 等均已添加到 `PATH`）。

```bash
spc dev:shell
```

可用于在 embed SAPI 的 `libphp.a` 上编译小型 C 程序，或手动检查构建环境。

