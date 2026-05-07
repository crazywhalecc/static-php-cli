# Installation

## Requirements

| Platform | Architecture | Notes |
|---|---|---|
| Linux | x86_64, aarch64 | Major distros supported (Alpine, Debian/Ubuntu, RHEL/CentOS, etc.) |
| macOS | x86_64 (Intel), arm64 (Apple Silicon) | macOS 12 or later |
| Windows | x86_64 | Windows 10 Build 17063 or later |

::: tip
Both glibc-based distros (Debian, Ubuntu, Arch, etc.) and musl-based ones (Alpine) are supported on Linux.
The `doctor` command will detect your environment and guide you through installing the right toolchain if needed.
:::

Pick the installation method that fits your use case:

| Method | Best for |
|---|---|
| Pre-built binary | Most users — download and run, no dependencies |
| From source | Contributors, or anyone who needs to modify core build logic |
| Vendor mode | Integrating StaticPHP into an existing PHP project |

## Pre-built binary

`spc` has no runtime dependencies — download the binary for your platform and it's ready to go.

> Fun fact: `spc` itself is a static PHP binary built with StaticPHP. We use StaticPHP to build StaticPHP's own build tool.

```shell
# Linux x86_64
curl -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64 -o spc
# Linux arm64
curl -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-aarch64 -o spc
# macOS x86_64 (Intel)
curl -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-x86_64 -o spc
# macOS arm64 (Apple Silicon)
curl -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-macos-aarch64 -o spc
# Windows x86_64 (PowerShell)
curl.exe -#fSL https://dl.static-php.dev/v3/spc-bin/nightly/spc-windows-x86_64.exe -o spc.exe
```

On Linux and macOS, mark the binary as executable before running it:

```bash
chmod +x spc && ./spc --version
```

## From source

This is the right path if you want to contribute to StaticPHP, or need to modify the core registry and build scripts. You'll need PHP >= 8.4, Composer, and the `mbstring,posix,pcntl,iconv,phar,zlib` extensions.

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git --branch v3
cd static-php-cli
composer install
```

If you don't have PHP or Composer installed, use the bundled setup script to install a self-contained runtime:

::: code-group
```bash [Linux / macOS]
bin/setup-runtime
```
```powershell [Windows]
.\bin\setup-runtime.ps1
.\bin\setup-runtime.ps1 add-path   # add runtime/ to PATH
```
:::

The script downloads `php` and `composer` into a `runtime/` subdirectory. You then have two options:

1. **Call them directly** (no PATH changes needed):
   ```bash
   runtime/php bin/spc --help
   runtime/php runtime/composer install
   ```

2. **Add `runtime/` to your PATH** so you can use `php`, `composer`, and `bin/spc` without prefixes:
   ```bash
   export PATH="/path/to/static-php-cli/runtime:$PATH"
   # Add this to ~/.bashrc or ~/.zshrc to make it permanent
   ```

## Vendor mode

If you already have a PHP project and want to call StaticPHP's build APIs directly, or use a custom registry to support private libraries and extensions, pull it in as a Composer dependency:

```bash
composer require crazywhalecc/static-php-cli
```

See the [Extending StaticPHP](../develop/extending/) guide for details.

## Verify your build environment

> **Vendor mode users can skip this step.**

Once installed, run `doctor` to check that your system has the required build tools (cmake, make, a C compiler, etc.):

```bash
# Using the spc binary
./spc doctor
# From source
bin/spc doctor
```

If anything is missing, `--auto-fix` will attempt to install it for you:

```bash
./spc doctor --auto-fix
```

Once `doctor` reports everything is good, head over to [First Build](./first-build).
