# Environment Variables

All environment variables mentioned in the list on this page have default values unless otherwise noted.
You can override the default values by setting these environment variables.

## Environment variables list

StaticPHP centralizes environment variables in the `config/env.ini` file.
You can set environment variables by modifying this file.

We divide the environment variables supported by StaticPHP into three types:

- **Global internal environment variables**: declared after StaticPHP starts, you can use `getenv()` to get them internally in StaticPHP, and you can override them before starting StaticPHP.
- **Fixed environment variables**: declared after StaticPHP starts, you can only use `getenv()` to get them, but you cannot override them through shell scripts.
- **Config file environment variables**: declared before StaticPHP builds, you can set these environment variables by modifying the `config/env.ini` file or through shell scripts.

You can read the comments for each parameter in [config/env.ini](https://github.com/crazywhalecc/static-php-cli/blob/v3/config/env.ini) to understand its purpose.

## Custom environment variables

Generally, you don't need to modify any of the following environment variables as they are already set to optimal values.
However, if you have special needs, you can set these environment variables to meet your needs
(for example, you need to debug PHP performance under different compilation parameters).

If you want to use custom environment variables, you can use the `export` command in the terminal or set the environment variables directly before the command, for example:

```shell
# export first
export SPC_CONCURRENCY=4
spc build:php "mbstring,pcntl" --build-cli

# or direct use
SPC_CONCURRENCY=4 spc build:php "mbstring,pcntl" --build-cli
```

Or, if you need to modify an environment variable for a long time, you can modify the `config/env.ini` file.

`config/env.ini` is divided into three sections: `[global]` is globally effective, `[windows]`, `[macos]`, `[linux]` are only effective for the corresponding operating system.

For example, if you need to modify the `./configure` command for compiling PHP, you can find the `SPC_CMD_PREFIX_PHP_CONFIGURE` environment variable in the `config/env.ini` file, and then modify its value.

If your build conditions are more complex and require multiple `env.ini` files to switch,
we recommend that you use the `config/env.custom.ini` file.
In this way, you can specify your environment variables by writing additional override items
without modifying the default `config/env.ini` file.

```ini
; This is an example of `config/env.custom.ini` file,
; we modify the `SPC_CONCURRENCY` and linux default CFLAGS passing to libs and PHP
[global]
SPC_CONCURRENCY=4

[linux]
SPC_DEFAULT_C_FLAGS="-O3"
```

