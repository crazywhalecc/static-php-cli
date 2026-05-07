---
outline: 'deep'
---

# PHP SAPI Reference

::: tip
If you installed spc as a pre-built binary, replace every `spc` in this page with `./spc` (or `.\spc.exe` on Windows).

If you installed from source, use `bin/spc` instead.
:::

This page describes the build options and usage for each PHP SAPI supported by StaticPHP.

## Overview

| SAPI | Build flag | Output path (Linux/macOS) | Output path (Windows) | Platform support |
|---|---|---|---|---|
| cli | `--build-cli` | `buildroot/bin/php` | `buildroot/bin/php.exe` | Linux, macOS, Windows |
| fpm | `--build-fpm` | `buildroot/bin/php-fpm` | — | Linux, macOS |
| micro | `--build-micro` | `buildroot/bin/micro.sfx` | `buildroot/bin/micro.sfx` | Linux, macOS, Windows |
| embed | `--build-embed` | `buildroot/lib/libphp.a` | `buildroot/lib/php8embed.lib` | Linux, macOS, Windows |
| frankenphp | `--build-frankenphp` | `buildroot/bin/frankenphp` | `buildroot/bin/frankenphp.exe` | Linux, macOS, Windows |

## cli

The `cli` SAPI is the standard PHP command-line binary for running scripts, interactive shells, and CLI applications.

### Build

```bash
spc build:php "bcmath,openssl,curl" --build-cli
```

The output is `buildroot/bin/php` on Linux and macOS, and `buildroot/bin/php.exe` on Windows.

See [build:php — SAPI Selection](./cli-reference#sapi-selection) and [build:php — Common Build Options](./cli-reference#common-build-options) for the full option reference.

### Usage

```bash
# Check version and loaded extensions
./buildroot/bin/php -v
./buildroot/bin/php -m

# Run a script
./buildroot/bin/php your-script.php

# Interactive mode
./buildroot/bin/php -a
```

### php.ini search path

The static PHP cli binary searches for `php.ini` in this order:

1. The path specified with the `-c /path/to/php.ini` command-line flag
2. The path set in the `PHP_INI_PATH` environment variable
3. The directory specified at compile time via `--with-config-file-path` (default: `/usr/local/etc/php`)

Run `./buildroot/bin/php --ini` to see which ini file is actually loaded.

### Hard-coded INI

Use `-I` at build time to bake INI settings directly into the binary, so no external `php.ini` is required:

```bash
spc build:php "bcmath,pcntl" --build-cli -I "memory_limit=4G" -I "disable_functions=system,exec"
```

Hard-coded INI applies to the `cli`, `micro`, and `embed` SAPIs.

## fpm

The `fpm` SAPI (FastCGI Process Manager) is used with web servers such as Nginx or Apache for traditional web application deployments.

::: warning
`fpm` is not supported on Windows.
:::

### Build

```bash
spc build:php "bcmath,openssl,curl,pdo_mysql" --build-fpm
```

The output is `buildroot/bin/php-fpm`.

See [build:php — SAPI Selection](./cli-reference#sapi-selection) and [build:php — Common Build Options](./cli-reference#common-build-options) for the full option reference.

### Usage

Copy `buildroot/bin/php-fpm` to your server and use it like a regular `php-fpm` binary:

```bash
# Check version
./buildroot/bin/php-fpm -v

# Start with a specific config file
./buildroot/bin/php-fpm -c /path/to/php.ini -y /path/to/php-fpm.conf

# Test config file
./buildroot/bin/php-fpm -t
```

### Example: Nginx + php-fpm

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Example `php-fpm.conf`:

```ini
[global]
pid = /var/run/php-fpm.pid
error_log = /var/log/php-fpm.log

[www]
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

## micro

The `micro` SAPI is built on [phpmicro](https://github.com/easysoft/phpmicro) and produces a self-contained executable stub. With `spc micro:combine`, you can merge `micro.sfx` with your PHP code into a single portable binary that requires no PHP installation on the target machine.

### Build

```bash
spc build:php "bcmath,phar,openssl,curl" --build-micro
```

The output is `buildroot/bin/micro.sfx`.

See [build:php — SAPI Selection](./cli-reference#sapi-selection), [build:php — Common Build Options](./cli-reference#common-build-options), and [build:php — micro Options](./cli-reference#micro-options) for the full option reference.

### Packaging an application

Use `micro:combine` to bundle a PHP script or phar into a standalone executable:

```bash
# Bundle a PHP script
echo "<?php echo 'Hello, World!' . PHP_EOL;" > hello.php
spc micro:combine hello.php --output=hello
./hello

# Bundle a phar
spc micro:combine your-app.phar --output=your-app
./your-app
```

### Injecting INI settings

INI configuration can be injected at packaging time via command-line options or an ini file:

```bash
# Inject via command-line options (-I is shorthand for --with-ini-set)
spc micro:combine your-app.phar --output=your-app -I "memory_limit=512M" -I "curl.cainfo=/etc/ssl/certs/ca-certificates.crt"

# Inject from an ini file (-N is shorthand for --with-ini-file)
spc micro:combine your-app.phar --output=your-app -N /path/to/custom.ini
```

::: tip
The INI injected with `-I` here is runtime configuration appended to the `micro.sfx` file as a special structure. This is distinct from INI hard-coded at compile time using `-I` during `build:php`. Both can coexist.
:::

### Pretending to be the cli SAPI

Some frameworks check the `PHP_SAPI` value and refuse to run outside `cli`. Since `micro`'s `PHP_SAPI` is `micro` by default, you can make it report `cli` instead:

```bash
spc build:php "bcmath,phar" --build-micro --with-micro-fake-cli
```

### Specifying a custom micro.sfx path

```bash
spc micro:combine your-app.phar --output=your-app --with-micro=/path/to/your/micro.sfx
```

### phar path considerations

When packaging a phar, internal relative paths may behave differently than expected. See the [Developer Guide — Phar directory issue](../develop/structure) for details.

## embed

The `embed` SAPI compiles PHP into a static library (`libphp.a` on Linux/macOS, `php8embed.lib` on Windows) that can be linked into C/C++ programs to run PHP code directly.

### Build

```bash
spc build:php "bcmath,openssl" --build-embed
```

Output:
- Linux/macOS: `buildroot/lib/libphp.a`, headers in `buildroot/include/`
- Windows: `buildroot/lib/php8embed.lib`, headers in `buildroot/include/`

See [build:php — SAPI Selection](./cli-reference#sapi-selection), [build:php — Common Build Options](./cli-reference#common-build-options), and [build:php — embed Options](./cli-reference#embed-options) for the full option reference.

::: tip
Detailed instructions for linking and using `libphp.a` / `php8embed.lib` in your own projects — including compiler selection, `dev:shell` usage, and a complete C example — will be covered in the Developer Guide.
:::

## frankenphp

The `frankenphp` SAPI builds a [FrankenPHP](https://github.com/php/frankenphp) binary — a modern PHP application server with Caddy built in, supporting HTTP/2, HTTP/3, automatic HTTPS, and more.

::: tip
The `frankenphp` binary produced by StaticPHP is a fully self-contained single-file executable. This is different from the official FrankenPHP release, which ships as a dynamically linked binary and requires a separate PHP installation.
:::

::: warning
FrankenPHP requires thread-safe mode. Always pass `--enable-zts` when building.
:::

### Build

```bash
spc build:php "bcmath,openssl,curl,pdo_mysql" --build-frankenphp --enable-zts
```

The output is `buildroot/bin/frankenphp` on Linux/macOS, and `buildroot/bin/frankenphp.exe` on Windows.

See [build:php — SAPI Selection](./cli-reference#sapi-selection), [build:php — Common Build Options](./cli-reference#common-build-options), and [build:php — frankenphp Options](./cli-reference#frankenphp-options) for the full option reference.

### Usage

```bash
# Check version
./buildroot/bin/frankenphp version

# Run in PHP development server mode
./buildroot/bin/frankenphp php-server

# Run with a Caddyfile
./buildroot/bin/frankenphp run --config /path/to/Caddyfile
```

For full usage, refer to the [FrankenPHP documentation](https://frankenphp.dev/docs/).

## Dynamic Extension Loading

Whether a static PHP binary can load extensions at runtime via `dl()` depends on how the binary was linked.

**macOS** — The build always links dynamically against system libraries. Extensions built as `.so` files can be loaded at runtime via `dl()` or `php.ini` as usual.

**Linux** — StaticPHP's default build target is `native-native-musl`: a fully static binary linked against musl libc. Because there is no dynamic linker available at runtime, `dl()` is disabled, the FFI extension cannot be used, and no external `.so` extensions can be loaded.

To support dynamic extension loading on Linux, set the `SPC_TARGET` environment variable before building:

```bash
SPC_TARGET=native-native-gnu.2.17 spc build:php "bcmath,openssl" --build-cli
```

If you installed from source, you can also set `SPC_TARGET=native-native-gnu.2.17` in `config/env.ini` to make it the default for all builds.

This uses the Zig toolchain to produce a partially static binary dynamically linked against glibc 2.17, compatible with most modern GNU/Linux distributions. No Docker and no extra cross-compilation toolchain are required. The resulting binary supports `dl()`, FFI, and loading `.so` extensions at runtime, but cannot run on musl-based systems such as Alpine Linux.

**Windows** — PHP extensions on Windows are distributed as `.dll` files that depend on the DLLs bundled with the official dynamically-built PHP. StaticPHP produces a standalone static executable that does not include those DLLs, so dynamic extension loading is not possible on Windows. All extensions must be compiled in statically at build time.

