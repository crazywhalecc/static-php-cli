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

| Option                          | Short | Description                                           |
|---------------------------------|-------|-------------------------------------------------------|
| `--for-extensions=<list>`       | `-e`  | Download artifacts needed by the given extensions     |
| `--for-libs=<list>`             | `-l`  | Download artifacts needed by the given libraries      |
| `--for-packages=<list>`         |       | Download artifacts needed by the given packages       |
| `--without-suggests`            |       | Skip suggested packages when using `--for-extensions` |
| `--clean`                       |       | Delete existing download cache before fetching        |
| `--with-php=<ver>`              |       | PHP version in `major.minor` format (default: `8.4`)  |
| `--prefer-binary`               | `-p`  | Prefer pre-built binaries over source archives        |
| `--prefer-source`               |       | Prefer source archives over pre-built binaries        |
| `--source-only`                 |       | Only download source artifacts                        |
| `--binary-only`                 |       | Only download binary artifacts                        |
| `--parallel=<n>`                | `-P`  | Number of parallel downloads (default: `1`)           |
| `--retry=<n>`                   | `-R`  | Number of retries on failure (default: `0`)           |
| `--ignore-cache=<list>`         |       | Force re-download the specified artifacts             |
| `--no-alt`                      |       | Do not use alternative mirror URLs                    |
| `--no-shallow-clone`            |       | Do not clone git repositories shallowly               |
| `--custom-url=<src:url>`        | `-U`  | Override the download URL for a source                |
| `--custom-git=<src:branch:url>` | `-G`  | Override with a custom git repository                 |
| `--custom-local=<src:path>`     | `-L`  | Use a local path as a source override                 |

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

| Option               | Description                                                   |
|----------------------|---------------------------------------------------------------|
| `--build-cli`        | Build the `cli` SAPI (`php` / `php.exe`)                      |
| `--build-fpm`        | Build `php-fpm` (Linux and macOS only)                        |
| `--build-cgi`        | Build `php-cgi`                                               |
| `--build-micro`      | Build `micro.sfx`                                             |
| `--build-embed`      | Build the embed static library (`libphp.a` / `php8embed.lib`) |
| `--build-frankenphp` | Build the FrankenPHP binary                                   |

### Common Build Options {#common-build-options}

| Option                               | Short | Description                                                                                            |
|--------------------------------------|-------|--------------------------------------------------------------------------------------------------------|
| `--no-strip`                         |       | Keep debug symbols; do not strip the binary                                                            |
| `--with-upx-pack`                    |       | Compress the output binary with UPX (install first with `spc install-pkg upx`; Linux and Windows only) |
| `--disable-opcache-jit`              |       | Disable OPcache JIT                                                                                    |
| `--with-config-file-path=<path>`     |       | Directory where PHP looks for `php.ini` (default: `/usr/local/etc/php`)                                |
| `--with-config-file-scan-dir=<path>` |       | Directory PHP scans for additional `.ini` files (default: `/usr/local/etc/php/conf.d`)                 |
| `--with-hardcoded-ini=<k=v>`         | `-I`  | Bake an INI setting into the binary at compile time (repeatable)                                       |
| `--enable-zts`                       |       | Enable thread-safe (ZTS) mode                                                                          |
| `--no-smoke-test`                    |       | Skip the post-build smoke tests                                                                        |
| `--with-suggests`                    |       | Also resolve and install suggested packages                                                            |
| `--with-packages=<list>`             |       | Additional packages to install alongside the build                                                     |
| `--no-download`                      |       | Skip the download step (use existing cached files)                                                     |
| `--build-shared=<list>`              | `-D`  | Extensions to compile as shared `.so` / `.dll` instead of static                                       |

### micro Options {#micro-options}

| Option                     | Description                                                                          |
|----------------------------|--------------------------------------------------------------------------------------|
| `--with-micro-fake-cli`    | Make `micro`'s `PHP_SAPI` report `cli` instead of `micro`                            |
| `--without-micro-ext-test` | Disable the post-build extension test for `micro.sfx`                                |
| `--with-micro-logo=<path>` | Embed a custom `.ico` icon into `micro.sfx` (Windows only)                           |
| `--enable-micro-win32`     | Build `micro.sfx` as a Win32 GUI application instead of a console app (Windows only) |

### frankenphp Options {#frankenphp-options}

| Option                         | Description                                       |
|--------------------------------|---------------------------------------------------|
| `--enable-zts`                 | Required for FrankenPHP; enables thread-safe mode |
| `--with-frankenphp-app=<path>` | Embed a directory into the FrankenPHP binary      |

### embed Options {#embed-options}

| Option                  | Description                                                           |
|-------------------------|-----------------------------------------------------------------------|
| `--build-shared=<list>` | Compile specific extensions as shared libraries (requires embed SAPI) |

### Download Pass-through Options {#download-options}

All downloader options are available with the `--dl-` prefix:

| Option                             | Description                                |
|------------------------------------|--------------------------------------------|
| `--dl-with-php=<ver>`              | PHP version to download (default: `8.4`)   |
| `--dl-prefer-binary`               | Prefer pre-built binaries for dependencies |
| `--dl-parallel=<n>`                | Number of parallel downloads               |
| `--dl-retry=<n>`                   | Number of retries on failure               |
| `--dl-custom-url=<src:url>`        | Override a source download URL             |
| `--dl-custom-git=<src:branch:url>` | Override with a custom git repository      |

Downloader options passed to `build:php` are used by the automatic downloader that runs before the build. 
This allows you to control the download behavior without needing a separate `spc download` command.

```bash
spc build:php "bcmath,openssl,curl" --build-cli --dl-with-php=8.3 --dl-prefer-binary --dl-parallel=4
```

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

## build:libs

Build one or more library packages from source.

```bash
spc build:libs <libraries> [options]
```

`libraries` (required): Comma-separated list of library package names to build (e.g. `"openssl,curl,zlib"`).

All `download` options are available with the `--dl-` prefix.

### Options

| Option | Short | Description |
|---|---|---|
| `--with-suggests` | `-L`, `-E` | Also resolve and install suggested packages |
| `--with-packages=<list>` | | Additional packages to install alongside the build, comma-separated |
| `--no-download` | | Skip downloading artifacts (use existing cached files) |

### Examples

```bash
# Build a single library
spc build:libs openssl

# Build multiple libraries
spc build:libs "openssl,curl,zlib"

# Build with suggested packages included
spc build:libs openssl --with-suggests

# Skip the download step
spc build:libs openssl --no-download
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

| Option             | Description                                                        |
|--------------------|--------------------------------------------------------------------|
| `--auto-fix`       | Automatically fix detected issues using the system package manager |
| `--auto-fix=never` | Report issues but never attempt automatic fixes                    |

## dev:shell

Enter an interactive shell with StaticPHP's build environment pre-loaded (compiler wrappers, `buildroot/`, `pkgroot/` paths, etc. on `PATH`).

```bash
spc dev:shell
```

Useful for compiling small programs against `libphp.a` (embed SAPI) or inspecting the build environment manually.

## check-update

Check whether newer versions are available for downloaded artifacts.

```bash
spc check-update [artifact] [options]
```

`artifact` (optional): Artifact names to check, comma-separated. Defaults to all currently downloaded artifacts.

### Options

| Option | Short | Description |
|---|---|---|
| `--json` | | Output results in JSON format |
| `--bare` | | Check without requiring the artifact to be downloaded first (old version will be `null`) |
| `--parallel=<n>` | `-p` | Number of parallel update checks (default: `10`) |
| `--with-php=<ver>` | | PHP version context in `major.minor` format (default: `8.4`) |

### Examples

```bash
# Check all downloaded artifacts
spc check-update

# Check specific artifacts
spc check-update "openssl,curl"

# Output as JSON
spc check-update --json

# Check without requiring a prior download
spc check-update "openssl" --bare
```

## dump-extensions

Analyse a Composer project and output the list of PHP extensions it requires.

```bash
spc dump-extensions [path] [options]
```

`path` (optional): Path to the project root (default: `.`).

### Options

| Option | Short | Description |
|---|---|---|
| `--format=<fmt>` | `-F` | Output format (default: `default`) |
| `--no-ext-output=<list>` | `-N` | When no extensions are found, output this default comma-separated list instead of exiting with failure |
| `--no-dev` | | Exclude dev dependencies |
| `--no-spc-filter` | `-S` | Do not apply the SPC filter when determining required extensions |

### Examples

```bash
# Analyse the current directory
spc dump-extensions

# Analyse a specific directory
spc dump-extensions /path/to/project

# Exclude dev dependencies
spc dump-extensions --no-dev

# Fall back to a default list when no extensions are found
spc dump-extensions --no-ext-output="bcmath,openssl"
```

## dump-license

Export open-source license files for artifacts.

```bash
spc dump-license [artifacts] [options]
```

`artifacts` (optional): Specific artifacts whose licenses should be dumped, comma-separated (e.g. `"php-src,openssl,curl"`).

### Options

| Option | Short | Description |
|---|---|---|
| `--for-extensions=<list>` | `-e` | Dump by extension names (automatically includes `php-src`), e.g. `"openssl,mbstring"` |
| `--for-libs=<list>` | `-l` | Dump by library names, e.g. `"openssl,zlib"` |
| `--for-packages=<list>` | `-p` | Dump by package names, e.g. `"php,libssl"` |
| `--dump-dir=<path>` | `-d` | Directory to write license files (default: `buildroot/license`) |
| `--without-suggests` | | Do not include licenses for suggested packages |

### Examples

```bash
# Dump licenses for the extensions you compiled
spc dump-license --for-extensions="bcmath,openssl,curl"

# Dump licenses for specific artifacts
spc dump-license "php-src,openssl"

# Write licenses to a custom directory
spc dump-license --for-extensions="bcmath,openssl" --dump-dir=/tmp/licenses
```

## extract

Extract downloaded artifacts to their target locations in the source tree.

```bash
spc extract [artifacts] [options]
```

`artifacts` (optional): Specific artifacts to extract, comma-separated (e.g. `"php-src,openssl,curl"`).

### Options

| Option | Short | Description |
|---|---|---|
| `--for-extensions=<list>` | `-e` | Extract artifacts needed by the given extensions, e.g. `"openssl,mbstring"` |
| `--for-libs=<list>` | `-l` | Extract artifacts needed by the given libraries, e.g. `"libcares,openssl"` |
| `--for-packages=<list>` | | Extract artifacts needed by the given packages, e.g. `"php,libssl,libcurl"` |
| `--without-suggests` | | Skip suggested packages when using `--for-extensions` |
| `--source-only` | | Force extraction from source even if a pre-built binary is available |

### Examples

```bash
# Extract artifacts for a set of extensions
spc extract --for-extensions="bcmath,openssl,curl"

# Extract specific artifacts
spc extract "php-src,openssl"

# Force source extraction
spc extract --for-extensions="bcmath,openssl" --source-only
```

## install-pkg

Install additional helper packages (e.g. UPX, toolchains). Aliases: `i`, `install-package`.

```bash
spc install-pkg <package> [options]
```

`package` (required): The name of the package to install.

All `download` options are available with the `--dl-` prefix.

### Examples

```bash
# Install the UPX compressor
spc install-pkg upx
```

## micro:combine

Merge `micro.sfx` with a PHP or PHAR file to produce a standalone executable.

```bash
spc micro:combine <file> [options]
```

`file` (required): Path to the PHP or PHAR file to combine.

### Options

| Option | Short | Description |
|---|---|---|
| `--with-micro=<path>` | `-M` | Path to a custom `micro.sfx` (default: `buildroot/bin/micro.sfx`) |
| `--with-ini-set=<k=v>` | `-I` | Inject an INI setting into the binary (repeatable) |
| `--with-ini-file=<path>` | `-N` | Inject INI settings from a file |
| `--output=<name>` | `-O` | Output file name (default: `my-app`) |

### Examples

```bash
# Combine a PHP script
spc micro:combine app.php

# Combine a PHAR with a custom output name
spc micro:combine app.phar --output my-app

# Inject INI settings
spc micro:combine app.php -I "memory_limit=512M" -I "disable_functions=system"

# Inject from an INI file
spc micro:combine app.php --with-ini-file=custom.ini

# Use a custom micro.sfx
spc micro:combine app.php --with-micro=/path/to/micro.sfx
```

## reset

Clean build directories and reset the build environment.

```bash
spc reset [options]
```

By default, removes `buildroot/` and `source/`.

### Options

| Option | Short | Description |
|---|---|---|
| `--with-pkgroot` | | Also remove the `pkgroot/` directory |
| `--with-download` | | Also remove the `downloads/` directory |
| `--yes` | `-y` | Skip the confirmation prompt |

### Examples

```bash
# Clean build directories (will prompt for confirmation)
spc reset

# Also clear the download cache
spc reset --with-download

# Full clean without prompting
spc reset --with-pkgroot --with-download --yes
```

## spc-config

Output compiler and linker flags needed to link your own program against the PHP embed static library.

```bash
spc spc-config [extensions] [options]
```

`extensions` (optional): Comma-separated list of extensions to include.

### Options

| Option | Short | Description |
|---|---|---|
| `--with-libs=<list>` | | Additional libraries to include, comma-separated |
| `--with-packages=<list>` | `-p` | Additional packages to include, comma-separated |
| `--with-suggested-libs` | `-L` | Include suggested libraries |
| `--with-suggests` | | Include all suggested packages |
| `--with-suggested-exts` | `-E` | Include suggested extensions |
| `--includes` | | Output only `-I` include paths (`CFLAGS`) |
| `--libs` | | Output only `-L` and `-l` linker flags (`LDFLAGS` + `LIBS`) |
| `--libs-only-deps` | | Output only `-l` dependency flags |
| `--absolute-libs` | | Use absolute paths for library files |
| `--no-php` | | Do not link against the PHP library |

### Examples

```bash
# Output full compiler + linker flags
spc spc-config "bcmath,openssl,curl"

# Output include paths only
spc spc-config "bcmath,openssl" --includes

# Output linker flags only
spc spc-config "bcmath,openssl" --libs

# Use absolute library paths
spc spc-config "bcmath,openssl" --libs --absolute-libs
```
