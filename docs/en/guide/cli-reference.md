---
outline: 'deep'
---

# CLI Reference

::: tip
If you installed spc as a pre-built binary, replace every `spc` in this page with `./spc` (or `.\spc.exe` on Windows).

If you installed from source, use `bin/spc` instead.
:::

## download

Download source archives and pre-built binaries required for building.

```bash
spc download [artifacts] [options]
```

`artifacts` (optional): Specific artifacts to download, comma-separated (e.g. `"php-src,openssl,curl"`).

### Options

| Option | Short | Description |
|---|---|---|
| `--for-extensions=<list>` | `-e` | Download artifacts needed by the given extensions |
| `--for-libs=<list>` | `-l` | Download artifacts needed by the given libraries |
| `--for-packages=<list>` | | Download artifacts needed by the given packages |
| `--without-suggests` | | Skip suggested packages when using `--for-extensions` |
| `--clean` | | Delete existing download cache before fetching |
| `--with-php=<ver>` | | PHP version in `major.minor` format (default: `8.4`) |
| `--prefer-binary` | `-p` | Prefer pre-built binaries over source archives |
| `--prefer-source` | | Prefer source archives over pre-built binaries |
| `--source-only` | | Only download source artifacts |
| `--binary-only` | | Only download binary artifacts |
| `--parallel=<n>` | `-P` | Number of parallel downloads (default: `1`) |
| `--retry=<n>` | `-R` | Number of retries on failure (default: `0`) |
| `--ignore-cache=<list>` | | Force re-download the specified artifacts |
| `--no-alt` | | Do not use alternative mirror URLs |
| `--no-shallow-clone` | | Do not clone git repositories shallowly |
| `--custom-url=<src:url>` | `-U` | Override the download URL for a source |
| `--custom-git=<src:branch:url>` | `-G` | Override with a custom git repository |
| `--custom-local=<src:path>` | `-L` | Use a local path as a source override |

### Examples

```bash
# Download only what the chosen extensions need
spc download --for-extensions="bcmath,openssl,curl" --with-php=8.4

# Download specific artifacts
spc download "php-src,openssl"

# Speed up with parallelism and retries
spc download --for-extensions="bcmath,openssl,curl" --parallel 8 --retry=3

# Prefer pre-built binaries
spc download --for-extensions="bcmath,openssl,curl" --prefer-binary

# Force re-download the PHP source (e.g. when switching versions)
spc download --for-extensions="bcmath,curl" --ignore-cache="php-src" --with-php=8.3

# Override a download URL
spc download --for-extensions="bcmath" --custom-url "php-src:https://downloads.php.net/~user/php-8.5.0alpha1.tar.xz"
```

## build:php {#build-php}

Build PHP and extensions from source. Alias: `build`.

```bash
spc build:php <extensions> [options]
```

`extensions` (required): Comma-separated list of extensions to compile statically (e.g. `"bcmath,openssl,curl"`).

All `download` options are also available on `build:php` with the `--dl-` prefix (e.g. `--dl-with-php=8.3`, `--dl-parallel=4`). These are passed to the automatic downloader that runs before the build.

### SAPI Selection {#sapi-selection}

These flags apply only to the combined `build:php` target. To build a specific SAPI in isolation, use its dedicated command (e.g. `spc build:php-cli`).

| Option | Description |
|---|---|
| `--build-cli` | Build the `cli` SAPI (`php` / `php.exe`) |
| `--build-fpm` | Build `php-fpm` (Linux and macOS only) |
| `--build-cgi` | Build `php-cgi` |
| `--build-micro` | Build `micro.sfx` |
| `--build-embed` | Build the embed static library (`libphp.a` / `php8embed.lib`) |
| `--build-frankenphp` | Build the FrankenPHP binary |

### Common Build Options {#common-build-options}

| Option | Short | Description |
|---|---|---|
| `--no-strip` | | Keep debug symbols; do not strip the binary |
| `--with-upx-pack` | | Compress the output binary with UPX (install first with `spc install-pkg upx`; Linux and Windows only) |
| `--disable-opcache-jit` | | Disable OPcache JIT |
| `--with-config-file-path=<path>` | | Directory where PHP looks for `php.ini` (default: `/usr/local/etc/php`) |
| `--with-config-file-scan-dir=<path>` | | Directory PHP scans for additional `.ini` files (default: `/usr/local/etc/php/conf.d`) |
| `--with-hardcoded-ini=<k=v>` | `-I` | Bake an INI setting into the binary at compile time (repeatable) |
| `--enable-zts` | | Enable thread-safe (ZTS) mode |
| `--no-smoke-test` | | Skip the post-build smoke tests |
| `--with-suggests` | `-L` / `-E` | Also resolve and install suggested packages |
| `--with-packages=<list>` | | Additional packages to install alongside the build |
| `--no-download` | | Skip the download step (use existing cached files) |
| `--with-added-patch=<file>` | `-P` | Inject an external PHP patch script (repeatable) |
| `--build-shared=<list>` | `-D` | Extensions to compile as shared `.so` / `.dll` instead of static |

### micro Options {#micro-options}

| Option | Description |
|---|---|
| `--with-micro-fake-cli` | Make `micro`'s `PHP_SAPI` report `cli` instead of `micro` |
| `--without-micro-ext-test` | Disable the post-build extension test for `micro.sfx` |
| `--with-micro-logo=<path>` | Embed a custom `.ico` icon into `micro.sfx` (Windows only) |
| `--enable-micro-win32` | Build `micro.sfx` as a Win32 GUI application instead of a console app (Windows only) |

### frankenphp Options {#frankenphp-options}

| Option | Description |
|---|---|
| `--enable-zts` | Required for FrankenPHP; enables thread-safe mode |
| `--with-frankenphp-app=<path>` | Embed a directory into the FrankenPHP binary |

### embed Options {#embed-options}

| Option | Description |
|---|---|
| `--build-shared=<list>` | Compile specific extensions as shared libraries (requires embed SAPI) |

### Download Pass-through Options {#download-options}

All downloader options are available with the `--dl-` prefix:

| Option | Description |
|---|---|
| `--dl-with-php=<ver>` | PHP version to download (default: `8.4`) |
| `--dl-prefer-binary` | Prefer pre-built binaries for dependencies |
| `--dl-parallel=<n>` | Number of parallel downloads |
| `--dl-retry=<n>` | Number of retries on failure |
| `--dl-custom-url=<src:url>` | Override a source download URL |
| `--dl-custom-git=<src:branch:url>` | Override with a custom git repository |

### Examples

```bash
# Build cli SAPI
spc build:php "bcmath,openssl,curl" --build-cli

# Build cli + micro together
spc build:php "bcmath,phar,openssl,curl" --build-cli --build-micro

# Build with a specific PHP version
spc build:php "bcmath,openssl" --build-cli --dl-with-php=8.3

# Bake INI into the binary
spc build:php "bcmath,pcntl" --build-cli -I "memory_limit=4G" -I "disable_functions=system"

# Keep debug symbols
spc build:php "bcmath,openssl" --build-cli --no-strip

# Build FrankenPHP (ZTS required)
spc build:php "bcmath,openssl,curl" --build-frankenphp --enable-zts
```

## build:php-cli, build:php-fpm, build:php-micro, build:php-embed, build:php-cgi, build:frankenphp

Dedicated single-target build commands. These accept the same options as `build:php` except the SAPI-selection flags (`--build-*`), which are implicit.

```bash
spc build:php-cli "bcmath,openssl,curl"
spc build:php-micro "bcmath,phar,openssl"
spc build:php-fpm "bcmath,openssl,curl,pdo_mysql"
spc build:php-embed "bcmath,openssl"
spc build:frankenphp "bcmath,openssl,curl" --enable-zts
```

## craft

Read a `craft.yml` and run the full build pipeline automatically.

```bash
spc craft [path/to/craft.yml]
```

If no path is given, `craft.yml` in the current working directory is used. See [craft.yml configuration](../develop/craft-yml) for the file format.

## doctor

Diagnose whether the current environment can compile PHP normally.

```bash
spc doctor [--auto-fix[=never]]
```

| Option | Description |
|---|---|
| `--auto-fix` | Automatically fix detected issues using the system package manager |
| `--auto-fix=never` | Report issues but never attempt automatic fixes |

## dev:shell

Enter an interactive shell with StaticPHP's build environment pre-loaded (compiler wrappers, `buildroot/`, `pkgroot/` paths, etc. on `PATH`).

```bash
spc dev:shell
```

Useful for compiling small programs against `libphp.a` (embed SAPI) or inspecting the build environment manually.
