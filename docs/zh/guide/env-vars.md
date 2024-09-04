---
aside: false
---

# 环境变量列表

本页面的环境变量列表中所提到的所有环境变量都具有默认值，除非另有说明。你可以通过设置这些环境变量来覆盖默认值。

一般情况下，你不需要修改任何以下环境变量，因为它们已经被设置为最佳值。
但是，如果你有特殊需求，你可以通过设置这些环境变量来满足你的需求（比如你需要调试不同编译参数下的 PHP 性能表现）。

如需使用自定义环境变量，你可以在终端中使用 `export` 命令或者在命令前直接设置环境变量，例如：

```shell
# export 方式
export SPC_CONCURRENCY=4
bin/spc build mbstring,pcntl --build-cli

# 直接设置方式
SPC_CONCURRENCY=4 bin/spc build mbstring,pcntl --build-cli
```

## 通用环境变量

通用环境变量是所有构建目标都可以使用的环境变量。

| var name                     | default value             | comment                     |
|------------------------------|---------------------------|-----------------------------|
| `BUILD_ROOT_PATH`            | `{pwd}/buildroot`         | 编译目标的根目录                    |
| `BUILD_LIB_PATH`             | `{pwd}/buildroot/lib`     | 编译依赖库的根目录                   |
| `BUILD_INCLUDE_PATH`         | `{pwd}/buildroot/include` | 编译依赖库的头文件目录                 |
| `BUILD_BIN_PATH`             | `{pwd}/buildroot/bin`     | 编译依赖库的二进制文件目录               |
| `PKG_ROOT_PATH`              | `{pwd}/pkgroot`           | 闭源或预编译工具下载后安装的目录            |
| `SOURCE_PATH`                | `{pwd}/source`            | 编译项目的源码解压缩目录                |
| `DOWNLOAD_PATH`              | `{pwd}/downloads`         | 下载的文件存放目录                   |
| `SPC_CONCURRENCY`            | 取决于当前 CPU 核心数量            | 并行编译的数量                     |
| `SPC_SKIP_PHP_VERSION_CHECK` | 空                         | 设置为 `yes` 时，跳过扩展对 PHP 版本的检查 |

## 系统特定变量

这些环境变量是特定于系统的，它们只在特定的系统上才会生效。

### Windows

| var name            | default value                                                                                                   | comment                                                                                    |
|---------------------|-----------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `PHP_SDK_PATH`      | `{pwd}\php-sdk-binary-tools`                                                                                    | PHP SDK 工具的安装目录                                                                            |
| `UPX_EXEC`          | `$PKG_ROOT_PATH\bin\upx.exe`                                                                                    | UPX 压缩工具的路径                                                                                |
| `SPC_MICRO_PATCHES` | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,cli_static` | 使用的 phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |

### macOS

| var name                             | default value                                                                                                                  | comment                                                                                    |
|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `CC`                                 | `clang`                                                                                                                        | C 编译器                                                                                      |
| `CXX`                                | `clang++`                                                                                                                      | C++ 编译器                                                                                    |
| `SPC_DEFAULT_C_FLAGS`                | `--target=arm64-apple-darwin` 或 `--target=x86_64-apple-darwin`                                                                 | 默认 C 编译标志（与 `CFLAGS` 不同）                                                                   |
| `SPC_DEFAULT_CXX_FLAGS`              | `--target=arm64-apple-darwin` 或 `--target=x86_64-apple-darwin`                                                                 | 默认 C++ 编译标志（与 `CXXFLAGS` 不同）                                                               |
| `SPC_CMD_PREFIX_PHP_BUILDCONF`       | `./buildconf --force`                                                                                                          | 编译 PHP `buildconf` 命令前缀                                                                    |
| `SPC_CMD_PREFIX_PHP_CONFIGURE`       | `./configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg` | 编译 PHP `configure` 命令前缀                                                                    |
| `SPC_CMD_PREFIX_PHP_MAKE`            | `make -j$SPC_CONCURRENCY`                                                                                                      | 编译 PHP `make` 命令前缀                                                                         |
| `SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS`   | `$SPC_DEFAULT_C_FLAGS -Werror=unknown-warning-option`                                                                          | PHP `configure` 命令的 `CFLAGS` 变量                                                            |
| `SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS` | `-I$BUILD_INCLUDE_PATH`                                                                                                        | PHP `configure` 命令的 `CPPFLAGS` 变量                                                          |
| `SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS`  | `-L$BUILD_LIB_PATH`                                                                                                            | PHP `configure` 命令的 `LDFLAGS` 变量                                                           |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`  | `-g0 -Os` 或 `-g -O0`（当使用 `--no-strip` 时为后者）                                                                                    | PHP `make` 命令的 `EXTRA_CFLAGS` 变量                                                           |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS`    | `-lresolv`                                                                                                                     | PHP `make` 命令的额外 `EXTRA_LIBS` 变量                                                           |
| `SPC_MICRO_PATCHES`                  | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,macos_iconv`               | 使用的 phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |

### Linux

| var name                                     | default value                                                                                                                                                | comment                                                                                    |
|----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `UPX_EXEC`                                   | `$PKG_ROOT_PATH/bin/upx`                                                                                                                                     | UPX 压缩工具的路径                                                                                |
| `GNU_ARCH`                                   | `x86_64` 或 `aarch64`                                                                                                                                         | 当前环境的 CPU 架构                                                                               |
| `CC`                                         | Alpine: `gcc`, Other: `$GNU_ARCH-linux-musl-gcc`                                                                                                             | C 编译器                                                                                      |
| `CXX`                                        | Alpine: `g++`, Other: `$GNU_ARCH-linux-musl-g++`                                                                                                             | C++ 编译器                                                                                    |
| `AR`                                         | Alpine: `ar`, Other: `$GNU_ARCH-linux-musl-ar`                                                                                                               | 静态库工具                                                                                      |
| `LD`                                         | `ld.gold`                                                                                                                                                    | 链接器                                                                                        |
| `PATH`                                       | `/usr/local/musl/bin:/usr/local/musl/$GNU_ARCH-linux-musl/bin:$PATH`                                                                                         | 系统 PATH                                                                                    |
| `SPC_DEFAULT_C_FLAGS`                        | empty                                                                                                                                                        | 默认 C 编译标志                                                                                  |
| `SPC_DEFAULT_CXX_FLAGS`                      | empty                                                                                                                                                        | 默认 C++ 编译标志                                                                                |
| `SPC_CMD_PREFIX_PHP_BUILDCONF`               | `./buildconf --force`                                                                                                                                        | 编译 PHP `buildconf` 命令前缀                                                                    |
| `SPC_CMD_PREFIX_PHP_CONFIGURE`               | `LD_LIBRARY_PATH={ld_lib_path} ./configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg` | 编译 PHP `configure` 命令前缀                                                                    |
| `SPC_CMD_PREFIX_PHP_MAKE`                    | `make -j$SPC_CONCURRENCY`                                                                                                                                    | 编译 PHP `make` 命令前缀                                                                         |
| `SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS`           | `$SPC_DEFAULT_C_FLAGS`                                                                                                                                       | PHP `configure` 命令的 `CFLAGS` 变量                                                            |
| `SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS`         | `-I$BUILD_INCLUDE_PATH`                                                                                                                                      | PHP `configure` 命令的 `CPPFLAGS` 变量                                                          |
| `SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS`          | `-L$BUILD_LIB_PATH`                                                                                                                                          | PHP `configure` 命令的 `LDFLAGS` 变量                                                           |
| `SPC_CMD_VAR_PHP_CONFIGURE_LIBS`             | `-ldl -lpthread`                                                                                                                                             | PHP `configure` 命令的 `LIBS` 变量                                                              |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`          | `-g0 -Os -fno-ident -fPIE` 或 `-g -O0 -fno-ident -fPIE`（当使用 `--no-strip` 时为后者）                                                                                | PHP `make` 命令的 `EXTRA_CFLAGS` 变量                                                           |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS`            | empty                                                                                                                                                        | PHP `make` 命令的额外 `EXTRA_LIBS` 变量                                                           |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM` | `-all-static`（当使用 `clang` 时：`-Xcompiler -fuse-ld=lld -all-static`）                                                                                           | `make` 命令的额外 `LDFLAGS` 变量（用于编译程序）                                                          |
| `SPC_NO_MUSL_PATH`                           | empty                                                                                                                                                        | 是否不插入 musl 工具链的 PATH（值为 `yes` 时不插入）                                                        |
| `SPC_MICRO_PATCHES`                          | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream`                                                                        | 使用的 phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |

> `{ld_lib_path}` 值为 `/usr/local/musl/$GNU_ARCH-linux-musl/lib`。

### FreeBSD

因 FreeBSD 系统的用户较少，我们暂时不提供 FreeBSD 系统的环境变量。

### Unix

对于 macOS、Linux、FreeBSD 等 Unix 系统，以下环境变量是通用的。

| var name          | default value                | comment          |
|-------------------|------------------------------|------------------|
| `PATH`            | `$BUILD_BIN_PATH:$PATH`      | 系统 PATH          |
| `PKG_CONFIG_PATH` | `$BUILD_LIB_PATH/pkgconfig`  | pkg-config 的搜索路径 |
| `PKG_CONFIG`      | `$BUILD_BIN_PATH/pkg-config` | pkg-config 命令路径  |

## 编译依赖库的环境变量（仅限 Unix 系统）

从 2.2.0 开始，static-php-cli 对所有 macOS、Linux、FreeBSD 等 Unix 系统的编译依赖库的命令均支持自定义环境变量。

这样你就可以随时通过环境变量来调整编译依赖库的行为。例如你可以通过 `xxx_CFLAGS=-O0` 来设置编译 xxx 库的优化参数。

当然，不是每个依赖库都支持注入环境变量，我们目前提供了三个通配的环境变量，后缀分别为：

- `_CFLAGS`: C 编译器的参数
- `_LDFLAGS`: 链接器的参数
- `_LIBS`: 额外的链接库

前缀为依赖库的名称，具体依赖库的名称以 `lib.json` 为准。其中，带有 `-` 的依赖库名称需要将 `-` 替换为 `_`。

下面是一个替换 openssl 库编译的优化选项示例：

```shell
openssl_CFLAGS="-O0"
```

库名称使用同 `lib.json` 中列举的名称，区分大小写。

::: tip
当未指定相关环境变量时，除以下变量外，其余值均默认为空：

| var name              | var default value                                                                               |
|-----------------------|-------------------------------------------------------------------------------------------------|
| `pkg_config_CFLAGS`   | macOS: `$SPC_DEFAULT_C_FLAGS -Wimplicit-function-declaration -Wno-int-conversion`, Other: empty |
| `pkg_config_LDFLAGS`  | Linux: `--static`, Other: empty                                                                 |
| `imagemagick_LDFLAGS` | Linux: `-static`, Other: empty                                                                  |
| `imagemagick_LIBS`    | macOS: `-liconv`, Other: empty                                                                  |
| `ldap_LDFLAGS`        | `-L$BUILD_LIB_PATH`                                                                             |
| `openssl_CFLAGS`      | Linux: `$SPC_DEFAULT_C_FLAGS`, Other: empty                                                     |
| others...             | empty                                                                                           |
:::

下表是支持自定义以上三种变量的依赖库名称列表：

| lib name    |
|-------------|
| brotli      |
| bzip        |
| curl        |
| freetype    |
| gettext     |
| gmp         |
| imagemagick |
| ldap        |
| libargon2   |
| libavif     |
| libcares    |
| libevent    |
| openssl     |

::: tip
因为给每个库适配自定义环境变量是一项特别繁琐的工作，且大部分情况下你都不需要这些库的自定义环境变量，所以我们目前只支持了部分库的自定义环境变量。

如果你需要自定义环境变量的库不在上方列表，可以通过 [GitHub Issue](https://github.com/crazywhalecc/static-php-cli/issues) 来提出需求。
:::
