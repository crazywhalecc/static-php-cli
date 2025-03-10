# Environment variables

All environment variables mentioned in the list on this page have default values unless otherwise noted. 
You can override the default values by setting these environment variables.

## Environment variables list

Starting from version 2.3.5, we have centralized the environment variables in the `config/env.ini` file.
You can set environment variables by modifying this file.

We divide the environment variables supported by static-php-cli into three types:

- Global internal environment variables: declared after static-php-cli starts, you can use `getenv()` to get them internally in static-php-cli, and you can override them before starting static-php-cli.
- Fixed environment variables: declared after static-php-cli starts, you can only use `getenv()` to get them, but you cannot override them through shell scripts.
- Config file environment variables: declared before static-php-cli build, you can set these environment variables by modifying the `config/env.ini` file or through shell scripts.

You can read the comments for each parameter in [config/env.ini](https://github.com/crazywhalecc/static-php-cli/blob/main/config/env.ini) to understand its purpose.

## Custom environment variables

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

Or, if you need to modify an environment variable for a long time, you can modify the `config/env.ini` file.

`config/env.ini` is divided into three sections, `[global]` is globally effective, `[windows]`, `[macos]`, `[linux]` are only effective for the corresponding operating system.

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

## Library environment variables (Unix only)

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
