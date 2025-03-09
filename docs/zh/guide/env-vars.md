# 环境变量

本页面的环境变量列表中所提到的所有环境变量都具有默认值，除非另有说明。你可以通过设置这些环境变量来覆盖默认值。

## 环境变量列表

在 2.3.5 版本之后，我们将环境变量集中到了 `config/env.ini` 文件中，你可以通过修改这个文件来设置环境变量。

我们将 static-php-cli 支持的环境变量分为三种：

- 全局内部环境变量：在 static-php-cli 启动后即声明，你可以在 static-php-cli 的内部使用 `getenv()` 来获取他们，也可以在启动 static-php-cli 前覆盖。
- 固定环境变量：在 static-php-cli 启动后声明，你仅可使用 `getenv()` 获取，但无法通过 shell 脚本对其覆盖。
- 配置文件环境变量：在 static-php-cli 构建前声明，你可以通过修改 `config/env.ini` 文件或通过 shell 脚本来设置这些环境变量。

你可以阅读 [config/env.ini](https://github.com/crazywhalecc/static-php-cli/blob/main/config/env.ini) 中每项参数的注释来了解其作用（仅限英文版）。

## 自定义环境变量

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

或者，如果你需要长期修改某个环境变量，你可以通过修改 `config/env.ini` 文件来实现。

`config/env.ini` 分为三段，其中 `[global]` 全局有效，`[windows]`、`[macos]`、`[linux]` 仅对应的操作系统有效。

例如，你需要修改编译 PHP 的 `./configure` 命令，你可以在 `config/env.ini` 文件中找到 `SPC_CMD_PREFIX_PHP_CONFIGURE` 环境变量，然后修改其值即可。

但如果你的构建条件比较复杂，需要多种 env.ini 进行切换，我们推荐你使用 `config/env.custom.ini` 文件，这样你可以在不修改默认的 `config/env.ini` 文件的情况下，
通过写入额外的重载项目指定你的环境变量。

```ini
; This is an example of `config/env.custom.ini` file, 
; we modify the `SPC_CONCURRENCY` and linux default CFLAGS passing to libs and PHP
[global]
SPC_CONCURRENCY=4

[linux]
SPC_DEFAULT_C_FLAGS="-O3"
```

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

如果你需要自定义环境变量的库不在上方列表，可以通过 [GitHub Issue](https://github.com/crazywhalecc/static-php-cli/issues)
来提出需求。
:::
