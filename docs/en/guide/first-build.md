# Your First Build

This page walks you through building a static PHP binary from scratch, end to end.

::: tip
If you installed spc as a pre-built binary, replace every `spc` in this page with `./spc` (or `.\spc.exe` on Windows).

If you installed from source, use `bin/spc` instead.
:::

## Two Approaches

StaticPHP supports two build workflows — pick the one that fits your situation:

| Approach | When to use |
|---|---|
| `craft` (one-shot) | Everyday use, getting started quickly |
| Step-by-step | Fine-grained control over the build pipeline |

## Option 1: One-Shot Build with `craft` (Recommended)

The `craft` command reads a `craft.yml` file and handles everything automatically — downloading dependencies, compiling libraries, and building PHP — in a single run.

### Write craft.yml

Create a `craft.yml` in your working directory and declare the PHP version, extensions, and target SAPIs:

```yaml
php-version: 8.4
extensions: bcmath,posix,phar,zlib,openssl,curl,fileinfo,tokenizer
sapi:
  - cli
  - micro
```

Not sure which extensions you need? Use the [command generator](./cli-generator) to produce a `craft.yml` automatically.

### Run the Build

```bash
spc craft
```

The build pipeline runs in order: download dependencies → compile libraries → compile PHP. No interaction required.

To see more detail, pass `-v`, `-vv`, or `-vvv`:

```bash
spc craft -v
```

### Inspect the Output

On success, binaries land in `buildroot/bin/`:

| SAPI | Output path |
|---|---|
| cli | `buildroot/bin/php` (Windows: `buildroot/bin/php.exe`) |
| fpm | `buildroot/bin/php-fpm` |
| micro | `buildroot/bin/micro.sfx` |
| embed | `buildroot/lib/libphp.a` |
| frankenphp | `buildroot/bin/frankenphp` |

Give the CLI binary a quick smoke-test:

```bash
./buildroot/bin/php -v
./buildroot/bin/php -m
```

## Option 2: Step-by-Step Build

This approach lets you run download and compile as separate steps — useful when you want to cache downloads in CI and reuse them across builds.

### Step 1: Download Dependencies

```bash
# Download only what the chosen extensions need (recommended)
spc download --for-extensions="bcmath,posix,phar,zlib,openssl,curl,fileinfo,tokenizer" --with-php=8.4

# Download by specific package names
spc download "curl,openssl" --with-php=8.4
```

Downloads are cached in `downloads/` and reused across builds automatically.

```bash
# Slow connection? Increase parallelism and retries
spc download --for-extensions="bcmath,openssl,curl" --parallel 10 --retry=3

# Use pre-built binaries where available — skips compiling those dependencies
spc download --for-extensions="bcmath,openssl,curl" --prefer-binary
```

### Step 2: Build PHP

```bash
# Build the cli SAPI
spc build:php "bcmath,phar,zlib,openssl,curl,fileinfo,tokenizer" --build-cli

# Build multiple SAPIs in one go
spc build:php "bcmath,phar,zlib,openssl,curl" --build-cli --build-micro
```

#### Common Build Options

| Option | Description |
|---|---|
| `--build-cli` | Build the cli SAPI |
| `--build-fpm` | Build php-fpm (not available on Windows) |
| `--build-micro` | Build micro.sfx |
| `--build-embed` | Build the embed SAPI |
| `--build-frankenphp` | Build FrankenPHP |
| `--enable-zts` | Enable thread-safe (ZTS) mode |
| `--no-strip` | Keep debug symbols; do not strip the binary |
| `-I key=value` | Hard-compile an INI option into PHP |
| `--with-upx-pack` | Compress output with UPX (run `spc install-pkg upx` first) |

Example — baking in a larger memory limit and disabling the `system` function:

```bash
spc build:php "bcmath,pcntl,posix" --build-cli -I "memory_limit=4G" -I "disable_functions=system"
```

## Packaging a micro App

Once you have `micro.sfx`, use `micro:combine` to bundle your PHP code into a single self-contained executable:

```bash
echo "<?php echo 'Hello, World!' . PHP_EOL;" > hello.php
spc micro:combine hello.php --output=hello
./hello
```

Works with `.phar` files too, and you can inject INI settings at packaging time:

```bash
# Bundle a phar
spc micro:combine your-app.phar --output=your-app

# Inject INI via command-line options
spc micro:combine your-app.phar --output=your-app -I "memory_limit=512M"

# Inject INI from a file
spc micro:combine your-app.phar --output=your-app -N /path/to/custom.ini
```

## Debugging and Rebuilding

If a build fails or you want to trace what's happening, use `-v` / `-vv` / `-vvv`:

```bash
spc build:php "bcmath,openssl" --build-cli -vv
```

- `-v` shows `INFO`-level logs: which modules are running and what build commands are being executed.
- `-vv` shows `DEBUG`-level logs: all internal debug output from StaticPHP.
- `-vvv` shows `DEBUG`-level logs and also pipes the stdout of every shell command directly to your terminal.

To wipe compiled artifacts and start fresh without re-downloading, run `reset`:

```bash
spc reset
# Then rebuild
spc build:php "bcmath,openssl" --build-cli
```

::: tip
`reset` only removes `buildroot/` and `source/`. Your `downloads/` cache is preserved.
Add `--with-download` if you also want to clear the download cache.
:::

If you're stuck, open an [Issue](https://github.com/static-php/static-php-cli/issues) and include your `craft.yml` (if any) and a zip of the `log/` directory.

## What's Next

- [PHP SAPI Reference](./sapi-reference) — Build options and usage guide for each PHP SAPI
- [CLI Reference](./cli-reference) — Full documentation for every command and option
- [Extensions](./extensions) — Browse supported extensions and their dependencies
- [Troubleshooting](./troubleshooting) — Diagnose common build failures

