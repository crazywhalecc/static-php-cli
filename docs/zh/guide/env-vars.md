# 环境变量

本页面的环境变量列表中所提到的所有环境变量都具有默认值，除非另有说明。你可以通过设置这些环境变量来覆盖默认值。

## 环境变量列表

StaticPHP 将环境变量集中到了 `config/env.ini` 文件中，你可以通过修改这个文件来设置环境变量。

我们将 StaticPHP 支持的环境变量分为三种：

- **全局内部环境变量**：在 StaticPHP 启动后即声明，你可以在 StaticPHP 的内部使用 `getenv()` 来获取他们，也可以在启动 StaticPHP 前覆盖。
- **固定环境变量**：在 StaticPHP 启动后声明，你仅可使用 `getenv()` 获取，但无法通过 shell 脚本对其覆盖。
- **配置文件环境变量**：在 StaticPHP 构建前声明，你可以通过修改 `config/env.ini` 文件或通过 shell 脚本来设置这些环境变量。

你可以阅读 [config/env.ini](https://github.com/crazywhalecc/static-php-cli/blob/v3/config/env.ini) 中每项参数的注释来了解其作用（仅限英文版）。

## 自定义环境变量

一般情况下，你不需要修改任何以下环境变量，因为它们已经被设置为最佳值。
但是，如果你有特殊需求，你可以通过设置这些环境变量来满足你的需求（比如你需要调试不同编译参数下的 PHP 性能表现）。

如需使用自定义环境变量，你可以在终端中使用 `export` 命令或者在命令前直接设置环境变量，例如：

```shell
# export 方式
export SPC_CONCURRENCY=4
spc build:php "mbstring,pcntl" --build-cli

# 直接设置方式
SPC_CONCURRENCY=4 spc build:php "mbstring,pcntl" --build-cli
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

