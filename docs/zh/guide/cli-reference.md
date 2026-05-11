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

| 选项 | 缩写   | 说明                                 |
|---|------|------------------------------------|
| `--for-extensions=<list>` | `-e` | 按扩展名下载其所需的制品                       |
| `--for-libs=<list>` | `-l` | 按库名下载其所需的制品                        |
| `--for-packages=<list>` |      | 按包名下载其所需的制品                        |
| `--without-suggests` |      | 使用 `--for-extensions` 时跳过建议包       |
| `--clean` |      | 下载前删除旧的下载缓存                        |
| `--with-php=<ver>` |      | PHP 版本，格式为 `major.minor`（默认 `8.5`） |
| `--prefer-binary` | `-p` | 优先使用预编译二进制                         |
| `--prefer-source` |      | 优先使用源码包                            |
| `--source-only` |      | 仅下载源码制品                            |
| `--binary-only` |      | 仅下载二进制制品                           |
| `--parallel=<n>` | `-P` | 并行下载数（默认 `1`）                      |
| `--retry=<n>` | `-R` | 失败重试次数（默认 `0`）                     |
| `--ignore-cache=<list>` | `-i` | 强制重新下载指定制品                         |
| `--no-alt` |      | 不使用镜像站                             |
| `--no-shallow-clone` |      | 不使用浅层克隆                            |
| `--custom-url=<src:url>` | `-U` | 覆盖指定源的下载地址                         |
| `--custom-git=<src:branch:url>` | `-G` | 覆盖为自定义 git 仓库                      |
| `--custom-local=<src:path>` | `-L` | 使用本地路径作为制品来源                       |

### 示例

```bash
# 按扩展名下载（推荐）
spc download --for-extensions="bcmath,openssl,curl" --with-php=8.5

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

| 选项                                   | 缩写   | 说明                                                     |
|--------------------------------------|------|--------------------------------------------------------|
| `--no-strip`                         |      | 保留调试符号，不精简二进制                                          |
| `--with-upx-pack`                    |      | 用 UPX 压缩产物（需先 `spc install-pkg upx`；仅 Linux 和 Windows） |
| `--disable-opcache-jit`              |      | 禁用 OPcache JIT                                         |
| `--with-config-file-path=<path>`     |      | PHP 查找 `php.ini` 的目录（默认：`/usr/local/etc/php`）          |
| `--with-config-file-scan-dir=<path>` |      | PHP 扫描追加 `.ini` 文件的目录（默认：`/usr/local/etc/php/conf.d`）  |
| `--with-hardcoded-ini=<k=v>`         | `-I` | 编译时将 INI 配置硬编码进二进制（可重复使用）                              |
| `--enable-zts`                       |      | 启用线程安全（ZTS）模式                                          |
| `--no-smoke-test`                    |      | 跳过构建后的冒烟测试                                             |
| `--with-suggests`                    |      | 同时解析并安装建议包                                             |
| `--with-packages=<list>`             |      | 额外安装的包                                                 |
| `--no-download`                      |      | 跳过下载步骤（使用已有缓存）                                         |
| `--build-shared=<list>`              | `-D` | 指定编译为共享 `.so` / `.dll` 的扩展                             |

### micro 专用选项 {#micro-options}

| 选项                         | 说明                                               |
|----------------------------|--------------------------------------------------|
| `--with-micro-fake-cli`    | 让 `micro` 的 `PHP_SAPI` 报告为 `cli` 而非 `micro`      |
| `--without-micro-ext-test` | 跳过构建后的 `micro.sfx` 扩展测试                          |
| `--with-micro-logo=<path>` | 为 `micro.sfx` 嵌入自定义 `.ico` 图标（仅 Windows）         |
| `--enable-micro-win32`     | 将 `micro.sfx` 构建为 Win32 GUI 程序而非控制台程序（仅 Windows） |

### frankenphp 专用选项 {#frankenphp-options}

| 选项 | 说明 |
|---|---|
| `--enable-zts` | FrankenPHP 必须开启线程安全 |
| `--with-frankenphp-app=<path>` | 将指定目录嵌入到 FrankenPHP 二进制中 |

### embed 专用选项 {#embed-options}

| 选项 | 说明 |
|---|---|
| `--build-shared=<list>` | 将指定扩展编译为共享库（需要 embed SAPI）|
| `--maintainer-skip-build` | （仅维护者）若 buildroot 中已存在 `libphp.a` / `libphp.so`，则跳过 PHP embed 的编译构建 |

### 下载透传选项 {#download-options}

所有下载器选项均可加 `--dl-` 前缀使用：

| 选项 | 说明                     |
|---|------------------------|
| `--dl-with-php=<ver>` | 指定下载的 PHP 版本（默认 `8.5`） |
| `--dl-prefer-binary` | 优先使用预编译二进制依赖           |
| `--dl-parallel=<n>` | 并行下载数                  |
| `--dl-retry=<n>` | 失败重试次数                 |
| `--dl-custom-url=<src:url>` | 覆盖指定源的下载地址             |
| `--dl-custom-git=<src:branch:url>` | 覆盖为自定义 git 仓库          |

Downloader 选项传递给 `build:php` 命令时，会被自动下载器在构建前使用。
这样你就可以直接通过构建命令控制下载行为，无需单独执行 `spc download` 命令。

```bash
spc build:php "bcmath,openssl,curl" --build-cli --dl-with-php=8.3 --dl-prefer-binary --dl-parallel=4
```

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

## build:libs

从源码构建一个或多个库包。

```bash
spc build:libs <libraries> [options]
```

`libraries`（必填）：要构建的库包名称列表，逗号分隔（如 `"openssl,curl,zlib"`）。

支持所有 `download` 命令的选项，加 `--dl-` 前缀传递。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--with-suggests` | `-L`、`-E` | 同时解析并安装建议包 |
| `--with-packages=<list>` | | 额外安装的包，逗号分隔 |
| `--no-download` | | 跳过下载步骤（使用已有缓存） |

### 示例

```bash
# 构建单个库
spc build:libs openssl

# 构建多个库
spc build:libs "openssl,curl,zlib"

# 构建时包含建议包
spc build:libs openssl --with-suggests

# 跳过下载步骤
spc build:libs openssl --no-download
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

| 选项                 | 说明                   |
|--------------------|----------------------|
| `--auto-fix`       | 自动修复检测到的问题（使用系统包管理器） |
| `--auto-fix=never` | 仅报告问题，不尝试自动修复        |

## dev:shell

进入加载了 StaticPHP 构建环境的交互式 Shell（编译器 wrapper、`buildroot/`、`pkgroot/` 等均已添加到 `PATH`）。

```bash
spc dev:shell
```

可用于在 embed SAPI 的 `libphp.a` 上编译小型 C 程序，或手动检查构建环境。

## check-update

检查指定制品是否有可用更新。

```bash
spc check-update [artifact] [options]
```

`artifact`（可选）：要检查更新的制品名称，逗号分隔。默认检查所有已下载的制品。

### 选项

| 选项 | 缩写 | 说明                                    |
|---|---|---------------------------------------|
| `--json` | | 以 JSON 格式输出结果                         |
| `--bare` | | 检查时不要求制品已下载（旧版本显示为 null）              |
| `--parallel=<n>` | `-p` | 并行检查数（默认 `10`）                        |
| `--with-php=<ver>` | | PHP 版本上下文，格式为 `major.minor`（默认 `8.5`） |

### 示例

```bash
# 检查所有已下载制品
spc check-update

# 检查指定制品
spc check-update "openssl,curl"

# 以 JSON 格式输出
spc check-update --json

# 无需先下载即可检查
spc check-update "openssl" --bare
```

## dump-extensions

从 Composer 项目中分析所需的 PHP 扩展列表。

```bash
spc dump-extensions [path] [options]
```

`path`（可选）：项目根目录路径，默认为当前目录（`.`）。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--format=<fmt>` | `-F` | 输出格式（默认 `default`）|
| `--no-ext-output=<list>` | `-N` | 未找到扩展时输出的默认组合（逗号分隔），而不是以失败退出 |
| `--no-dev` | | 不包含 dev 依赖 |
| `--no-spc-filter` | `-S` | 不使用 SPC 过滤器筛选扩展 |

### 示例

```bash
# 分析当前目录的 Composer 项目
spc dump-extensions

# 分析指定目录
spc dump-extensions /path/to/project

# 不包含 dev 依赖
spc dump-extensions --no-dev

# 未找到扩展时输出默认组合
spc dump-extensions --no-ext-output="bcmath,openssl"
```

## dump-license

导出制品的开源许可证文件。

```bash
spc dump-license [artifacts] [options]
```

`artifacts`（可选）：要导出许可证的制品名称，逗号分隔（如 `"php-src,openssl,curl"`）。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--for-extensions=<list>` | `-e` | 按扩展名导出（自动包含 php-src），如 `"openssl,mbstring"` |
| `--for-libs=<list>` | `-l` | 按库名导出，如 `"openssl,zlib"` |
| `--for-packages=<list>` | `-p` | 按包名导出，如 `"php,libssl"` |
| `--dump-dir=<path>` | `-d` | 许可证输出目录（默认 `buildroot/license`）|
| `--without-suggests` | | 不包含建议包的许可证 |

### 示例

```bash
# 按扩展名导出许可证
spc dump-license --for-extensions="bcmath,openssl,curl"

# 导出指定制品的许可证
spc dump-license "php-src,openssl"

# 指定输出目录
spc dump-license --for-extensions="bcmath,openssl" --dump-dir=/tmp/licenses
```

## extract

将已下载的制品解压到对应的目标位置。

```bash
spc extract [artifacts] [options]
```

`artifacts`（可选）：要解压的制品名称，逗号分隔（如 `"php-src,openssl,curl"`）。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--for-extensions=<list>` | `-e` | 按扩展名解压所需制品，如 `"openssl,mbstring"` |
| `--for-libs=<list>` | `-l` | 按库名解压所需制品，如 `"libcares,openssl"` |
| `--for-packages=<list>` | | 按包名解压所需制品，如 `"php,libssl,libcurl"` |
| `--without-suggests` | | 使用 `--for-extensions` 时跳过建议包 |
| `--source-only` | | 强制解压源码，即使已有预编译二进制 |

### 示例

```bash
# 按扩展名解压
spc extract --for-extensions="bcmath,openssl,curl"

# 解压指定制品
spc extract "php-src,openssl"

# 强制解压源码
spc extract --for-extensions="bcmath,openssl" --source-only
```

## install-pkg

安装额外的辅助包（如 UPX、工具链等）。别名：`i`、`install-package`。

```bash
spc install-pkg <package> [options]
```

`package`（必填）：要安装的包名称。

支持所有 `download` 命令的选项，加 `--dl-` 前缀传递。

### 示例

```bash
# 安装 UPX 压缩工具
spc install-pkg upx
```

## micro:combine

将 `micro.sfx` 与 PHP/PHAR 文件合并为独立可执行文件。

```bash
spc micro:combine <file> [options]
```

`file`（必填）：要合并的 PHP 或 PHAR 文件路径。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--with-micro=<path>` | `-M` | 指定自定义 `micro.sfx` 文件路径（默认使用 `buildroot/bin/micro.sfx`）|
| `--with-ini-set=<k=v>` | `-I` | 合并时注入 INI 配置（可重复使用）|
| `--with-ini-file=<path>` | `-N` | 合并时注入 INI 文件 |
| `--output=<name>` | `-O` | 自定义输出文件名（默认 `my-app`）|

### 示例

```bash
# 合并 PHP 文件
spc micro:combine app.php

# 合并 PHAR 文件并指定输出名
spc micro:combine app.phar --output my-app

# 注入 INI 配置
spc micro:combine app.php -I "memory_limit=512M" -I "disable_functions=system"

# 注入 INI 文件
spc micro:combine app.php --with-ini-file=custom.ini

# 使用自定义 micro.sfx
spc micro:combine app.php --with-micro=/path/to/micro.sfx
```

## reset

清理构建目录，重置构建环境。

```bash
spc reset [options]
```

默认清理 `buildroot/` 和 `source/` 目录。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--with-pkgroot` | | 同时删除 `pkgroot/` 目录 |
| `--with-download` | | 同时删除 `downloads/` 目录 |
| `--yes` | `-y` | 跳过确认提示 |

### 示例

```bash
# 清理构建目录（会提示确认）
spc reset

# 同时清理下载缓存
spc reset --with-download

# 完全清理（不提示）
spc reset --with-pkgroot --with-download --yes
```

## spc-config

输出静态编译所需的编译器和链接器标志，适用于将 PHP embed 库链接到自定义程序。

```bash
spc spc-config [extensions] [options]
```

`extensions`（可选）：要包含的扩展名列表，逗号分隔。

### 选项

| 选项 | 缩写 | 说明 |
|---|---|---|
| `--with-libs=<list>` | | 额外包含的库，逗号分隔 |
| `--with-packages=<list>` | `-p` | 额外包含的包，逗号分隔 |
| `--with-suggested-libs` | `-L` | 包含建议库 |
| `--with-suggests` | | 包含所有建议包 |
| `--with-suggested-exts` | `-E` | 包含建议扩展 |
| `--includes` | | 仅输出 `-I` 头文件路径（`CFLAGS`）|
| `--libs` | | 仅输出 `-L` 和 `-l` 链接标志（`LDFLAGS + LIBS`）|
| `--libs-only-deps` | | 仅输出依赖库的 `-l` 标志 |
| `--absolute-libs` | | 使用库文件的绝对路径输出 |
| `--no-php` | | 不链接 PHP 库 |

### 示例

```bash
# 输出完整编译标志
spc spc-config "bcmath,openssl,curl"

# 仅输出头文件路径
spc spc-config "bcmath,openssl" --includes

# 仅输出链接标志
spc spc-config "bcmath,openssl" --libs

# 使用绝对路径
spc spc-config "bcmath,openssl" --libs --absolute-libs
```

