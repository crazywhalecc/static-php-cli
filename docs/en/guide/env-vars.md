---
aside: false
---

# Environment variables

All environment variables mentioned in the list on this page have default values unless otherwise noted. 
You can override the default values by setting these environment variables.

Generally, you don't need to modify any of the following environment variables as they are already set to optimal values.
However, if you have special needs, you can set these environment variables to meet your needs 
(for example, you need to debug PHP performance under different compilation parameters).

If you want to use custom environment variables, you can use the `export` command in the terminal or set the environment variables directly before the command, for example:

```shell
# export first
export SPC_CONCURRENCY=4
bin/spc build mbstring,pcntl --build-cli

# or direct use
SPC_CONCURRENCY=4 bin/spc build mbstring,pcntl --build-cli
```

## General environment variables

General environment variables can be used by all build targets.

| var name                     | default value             | comment                                         |
|------------------------------|---------------------------|-------------------------------------------------|
| `BUILD_ROOT_PATH`            | `{pwd}/buildroot`         | The root directory of the build target          |
| `BUILD_LIB_PATH`             | `{pwd}/buildroot/lib`     | The root directory of compilation libraries     |
| `BUILD_INCLUDE_PATH`         | `{pwd}/buildroot/include` | Header file directory for compiling libraries   |
| `BUILD_BIN_PATH`             | `{pwd}/buildroot/bin`     | Compiled binary file directory                  |
| `PKG_ROOT_PATH`              | `{pwd}/pkgroot`           | Directory where precompiled tools are installed |
| `SOURCE_PATH`                | `{pwd}/source`            | The source code extract directory               |
| `DOWNLOAD_PATH`              | `{pwd}/downloads`         | Downloaded file directory                       |
| `SPC_CONCURRENCY`            | Depends on CPU cores      | Number of parallel compilations                 |
| `SPC_SKIP_PHP_VERSION_CHECK` | empty                     | Skip PHP version check when set to `yes`        |

## OS specific variables

These environment variables are system-specific and will only take effect on a specific OS.

### Windows

| var name            | default value                                                                                                   | comment                                                                                     |
|---------------------|-----------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| `PHP_SDK_PATH`      | `{pwd}\php-sdk-binary-tools`                                                                                    | PHP SDK tools path                                                                          |
| `UPX_EXEC`          | `$PKG_ROOT_PATH\bin\upx.exe`                                                                                    | UPX compression tool path                                                                   |
| `SPC_MICRO_PATCHES` | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,cli_static` | Used phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |

### macOS

| var name                             | default value                                                                                                                  | comment                                                                                     |
|--------------------------------------|--------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| `CC`                                 | `clang`                                                                                                                        | C Compiler                                                                                  |
| `CXX`                                | `clang++`                                                                                                                      | C++ Compiler                                                                                |
| `SPC_DEFAULT_C_FLAGS`                | `--target=arm64-apple-darwin` or `--target=x86_64-apple-darwin`                                                                | Default C flags (not the same as `CFLAGS`)                                                  |
| `SPC_DEFAULT_CXX_FLAGS`              | `--target=arm64-apple-darwin` or `--target=x86_64-apple-darwin`                                                                | Default C flags (not the same as `CPPFLAGS`)                                                |
| `SPC_CMD_PREFIX_PHP_BUILDCONF`       | `./buildconf --force`                                                                                                          | PHP `buildconf` command prefix                                                              |
| `SPC_CMD_PREFIX_PHP_CONFIGURE`       | `./configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg` | PHP `configure` command prefix                                                              |
| `SPC_CMD_PREFIX_PHP_MAKE`            | `make -j$SPC_CONCURRENCY`                                                                                                      | PHP `make` command prefix                                                                   |
| `SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS`   | `$SPC_DEFAULT_C_FLAGS -Werror=unknown-warning-option`                                                                          | `CFLAGS` variable of PHP `configure` command                                                |
| `SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS` | `-I$BUILD_INCLUDE_PATH`                                                                                                        | `CPPFLAGS` variable of PHP `configure` command                                              |
| `SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS`  | `-L$BUILD_LIB_PATH`                                                                                                            | `LDFLAGS` variable of PHP `configure` command                                               |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`  | `-g0 -Os` or `-g -O0` (the latter when using `--no-strip`)                                                                     | `EXTRA_CFLAGS` variable of PHP `make` command                                               |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS`    | `-lresolv`                                                                                                                     | Extra `EXTRA_LIBS` variables for PHP `make` command                                         |
| `SPC_MICRO_PATCHES`                  | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream,macos_iconv`               | Used phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |

### Linux

| var name                                     | default value                                                                                                                                                | comment                                                                                     |
|----------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| `UPX_EXEC`                                   | `$PKG_ROOT_PATH/bin/upx`                                                                                                                                     | UPX compression tool path                                                                   |
| `GNU_ARCH`                                   | `x86_64` or `aarch64`                                                                                                                                        | CPU architecture                                                                            |
| `CC`                                         | Alpine: `gcc`, Other: `$GNU_ARCH-linux-musl-gcc`                                                                                                             | C Compiler                                                                                  |
| `CXX`                                        | Alpine: `g++`, Other: `$GNU_ARCH-linux-musl-g++`                                                                                                             | C++ Compiler                                                                                |
| `AR`                                         | Alpine: `ar`, Other: `$GNU_ARCH-linux-musl-ar`                                                                                                               | Static library tools                                                                        |
| `LD`                                         | `ld.gold`                                                                                                                                                    | Linker                                                                                      |
| `PATH`                                       | `/usr/local/musl/bin:/usr/local/musl/$GNU_ARCH-linux-musl/bin:$PATH`                                                                                         | System PATH                                                                                 |
| `SPC_DEFAULT_C_FLAGS`                        | empty                                                                                                                                                        | Default C flags                                                                             |
| `SPC_DEFAULT_CXX_FLAGS`                      | empty                                                                                                                                                        | Default C++ flags                                                                           |
| `SPC_CMD_PREFIX_PHP_BUILDCONF`               | `./buildconf --force`                                                                                                                                        | PHP `buildconf` command prefix                                                              |
| `SPC_CMD_PREFIX_PHP_CONFIGURE`               | `LD_LIBRARY_PATH={ld_lib_path} ./configure --prefix= --with-valgrind=no --enable-shared=no --enable-static=yes --disable-all --disable-cgi --disable-phpdbg` | PHP `configure` command prefix                                                              |
| `SPC_CMD_PREFIX_PHP_MAKE`                    | `make -j$SPC_CONCURRENCY`                                                                                                                                    | PHP `make` command prefix                                                                   |
| `SPC_CMD_VAR_PHP_CONFIGURE_CFLAGS`           | `$SPC_DEFAULT_C_FLAGS`                                                                                                                                       | `CFLAGS` variable of PHP `configure` command                                                |
| `SPC_CMD_VAR_PHP_CONFIGURE_CPPFLAGS`         | `-I$BUILD_INCLUDE_PATH`                                                                                                                                      | `CPPFLAGS` variable of PHP `configure` command                                              |
| `SPC_CMD_VAR_PHP_CONFIGURE_LDFLAGS`          | `-L$BUILD_LIB_PATH`                                                                                                                                          | `LDFLAGS` variable of PHP `configure` command                                               |
| `SPC_CMD_VAR_PHP_CONFIGURE_LIBS`             | `-ldl -lpthread`                                                                                                                                             | `LIBS` variable of PHP `configure` command                                                  |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`          | `-g0 -Os -fno-ident -fPIE` or `-g -O0 -fno-ident -fPIE` (the latter when using `--no-strip`)                                                                 | `EXTRA_CFLAGS` variable of PHP `make` command                                               |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS`            | empty                                                                                                                                                        | Extra `EXTRA_LIBS` variables for PHP `make` command                                         |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM` | `-all-static` (when using `clang`: `-Xcompiler -fuse-ld=lld -all-static`)                                                                                    | Additional `LDFLAGS` variable for `make` command                                            |
| `SPC_NO_MUSL_PATH`                           | empty                                                                                                                                                        | Whether to not insert the PATH of the musl toolchain (not inserted when the value is `yes`) |
| `SPC_MICRO_PATCHES`                          | `static_extensions_win32,cli_checks,disable_huge_page,vcruntime140,win32,zend_stream`                                                         | Used phpmicro [patches](https://github.com/easysoft/phpmicro/blob/master/patches/Readme.md) |
> `{ld_lib_path}` value is `/usr/local/musl/$GNU_ARCH-linux-musl/lib`。

### FreeBSD

Due to the small number of users of the FreeBSD system, we do not provide environment variables for the FreeBSD system for the time being.

### Unix

For Unix systems such as macOS, Linux, FreeBSD, etc., the following environment variables are common.

| var name          | default value                | comment                    |
|-------------------|------------------------------|----------------------------|
| `PATH`            | `$BUILD_BIN_PATH:$PATH`      | System PATH                |
| `PKG_CONFIG_PATH` | `$BUILD_LIB_PATH/pkgconfig`  | pkg-config search path     |
| `PKG_CONFIG`      | `$BUILD_BIN_PATH/pkg-config` | pkg-config executable path |

## Library Environment variables (Unix only)

Starting from 2.2.0, static-php-cli supports custom environment variables for all compilation dependent library commands of macOS, Linux, FreeBSD and other Unix systems.

In this way, you can adjust the behavior of compiling dependent libraries through environment variables at any time. 
For example, you can set the optimization parameters for compiling the xxx library through `xxx_CFLAGS=-O0`.

Of course, not every library supports the injection of environment variables. 
We currently provide three wildcard environment variables with the suffixes:

- `_CFLAGS`: CFLAGS for the compiler
- `_LDFLAGS`: LDFLAGS for the linker
- `_LIBS`: LIBS for the linker

The prefix is the name of the dependent library, and the specific name of the library is subject to `lib.json`. 
Among them, the library name with `-` needs to replace `-` with `_`.

Here is an example of an optimization option that replaces the openssl library compilation:

```shell
openssl_CFLAGS="-O0"
```

The library name uses the same name listed in `lib.json` and is case-sensitive.

::: tip
When no relevant environment variables are specified, except for the following variables, the remaining values are empty by default:

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

The following table is a list of library names that support customizing the above three variables:

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
Because adapting custom environment variables to each library is a particularly tedious task, 
and in most cases you do not need custom environment variables for these libraries, 
so we currently only support custom environment variables for some libraries.

If the library you need to customize environment variables is not listed above, 
you can submit your request through [GitHub Issue](https://github.com/crazywhalecc/static-php-cli/issues).
:::
