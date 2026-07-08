# Migrating from v2

StaticPHP v3 is a ground-up rewrite. The core build workflow (`download → build → combine`) remains familiar, but several commands, options, and configuration fields have changed. This page covers everything you need to update before switching.

::: info Scope
This guide only covers user-facing CLI commands, options, `craft.yml` fields, and `env.ini` variable names. Internal PHP APIs are not covered.
:::

## Documentation URL Change

The official documentation site has moved:

- **v3 docs (current)**: [https://static-php.dev](https://static-php.dev) — the main site now hosts v3 documentation.
- **v2 docs (archived)**: [https://static-php.github.io/v2-docs/](https://static-php.github.io/v2-docs/) — v2 documentation is preserved here for reference.

Update any bookmarks or internal links accordingly.

## `spc` Binary Download URL Change

The nightly `spc` self-contained binary has moved to a new path:

| | URL |
|---|---|
| **v2** | `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/` |
| **v3** | `https://dl.static-php.dev/v3/spc-bin/nightly/` |

Update any CI scripts or bootstrap commands that download the `spc` binary directly. For example:

```bash
# v2
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64

# v3
curl -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64
```

## Removed Commands

| v2 Command | v3 Replacement | Notes |
|---|---|---|
| `del-download` | `spc reset` | `reset` also accepts `--with-pkgroot` and `--with-download` for finer control |
| `del-download --all` | `spc reset --with-download` | Removes the downloads cache directory |

## Removed Options

### `--with-added-patch` / `-P` (build command)

This option allowed injecting external PHP patch scripts at specific build stages. **It has been removed in v3.**

There is no direct drop-in replacement. If you relied on this feature:

- Consider contributing your patches upstream to the StaticPHP repository.
- For project-specific patches, use a custom registry with a package class. See [Writing Package Classes](/en/develop/extending/package-classes) for details.

::: tip Future Plans
A single-file hook API for lightweight patches may be provided in a future release.
:::

### Windows-only: `--with-sdk-binary-dir` and `--vs-ver`

These options are no longer accepted on the command line. In v3, the `php-sdk-binary-tools` dependency has been completely removed. v3 now manages its own **MSYS2** environment to support autotools-based library builds on Windows. Run `spc doctor --install` to download and configure MSYS2 automatically.

If you need to point to a custom MSYS2 installation, set the `SPC_MSYS2_PATH` environment variable to the `msys64` directory (e.g. `C:\msys64`). Visual Studio is now auto-detected by the toolchain — no manual version flag needed.

::: warning Migrating from v2
v2 relied on `php-sdk-binary-tools` and required `--with-sdk-binary-dir` and `--vs-ver` on every build invocation. In v3 these options are gone. Remove them from all CI scripts and run `spc doctor --install` once to set up the Windows build environment.
:::

## Renamed / Deprecated Options

The following options have been renamed. The old names are accepted where noted, but you should update your scripts.

| v2 Option | v3 Option | Status |
|---|---|---|
| `--prefer-pre-built` | `--prefer-binary` / `-p` | Old name kept as a deprecated alias |
| `--with-libs=<list>` | `--with-packages=<list>` | — |
| `--with-suggested-libs` / `-L` | `--with-suggests` | Old `-L` / `-E` flags removed |
| `--with-suggested-exts` / `-E` | `--with-suggests` | Merged into a single flag |

### Example

```bash
# v2
spc build curl,gd --build-cli --with-libs="openssl" -L -E

# v3
spc build curl,gd --build-cli --with-packages="openssl" --with-suggests
```

## Changed `build` Command Behaviour

The `build` command (alias: `build:php`) still works. However, v3 also provides **dedicated single-target commands** that do not require SAPI selection flags:

| v2 | v3 Equivalent |
|---|---|
| `spc build exts --build-cli` | `spc build:php-cli exts` |
| `spc build exts --build-fpm` | `spc build:php-fpm exts` |
| `spc build exts --build-cgi` | `spc build:php-cgi exts` |
| `spc build exts --build-micro` | `spc build:php-micro exts` |
| `spc build exts --build-embed` | `spc build:php-embed exts` |
| `spc build exts --build-frankenphp` | `spc build:frankenphp exts` |

Use `build:php` when you need to build multiple SAPIs in one pass (the `--build-*` flags remain valid there).

### Automatic Download in Build Commands

In v3, all `build:*` commands automatically download any missing dependencies before building. You no longer need to run `spc download` as a separate step:

```bash
# v2 — two steps required
spc download --for-extensions=curl,gd
spc build curl,gd --build-cli

# v3 — one step is enough
spc build:php-cli curl,gd
```

To opt out of the automatic download (for example in CI where sources are pre-cached), pass `--no-download`:

```bash
spc build:php-cli curl,gd --no-download
```

## Changed `download` Command Options

| v2 | v3 | Notes |
|---|---|---|
| `--prefer-pre-built` | `--prefer-binary` / `-p` | Deprecated alias kept |
| `--with-libs` | `--for-libs` | Separate from packages |
| *(no equivalent)* | `--for-packages` | Unified package filter |
| *(no equivalent)* | `--parallel` / `-P` | Parallel downloads |
| *(no equivalent)* | `--retry` / `-R` | Retry on failure |

## Removed Dev Commands

These development utility commands have been removed or consolidated:

| v2 Command | v3 Replacement |
|---|---|
| `dev:extensions` / `list-ext` | `spc dev:info <package>` |
| `dev:ext-version` / `dev:ext-ver` | `spc dev:info <package>` |
| `dev:lib-version` / `dev:lib-ver` | `spc dev:info <package>` |
| `dev:php-version` / `dev:php-ver` | `spc dev:info php-src` |
| `dev:gen-ext-dep-docs` + `dev:gen-lib-dep-docs` | `spc dev:gen-deps-data` |

## Renamed Dev Commands

| v2 | v3 | Notes |
|---|---|---|
| `dev:sort-config` / `sort-config` | `dev:lint-config` | Old alias still accepted |

## New Commands in v3

These commands are new in v3 with no v2 equivalent:

| Command | Description |
|---|---|
| `spc reset` | Clean `buildroot/` and `source/` directories |
| `spc check-update` | Check for newer artifact versions |
| `spc build:php-cli` | Build CLI SAPI (no flags needed) |
| `spc build:php-fpm` | Build PHP-FPM (no flags needed) |
| `spc build:php-cgi` | Build PHP CGI (no flags needed) |
| `spc build:php-micro` | Build phpmicro (no flags needed) |
| `spc build:php-embed` | Build embed SAPI (no flags needed) |
| `spc build:frankenphp` | Build FrankenPHP (no flags needed) |
| `spc dev:shell` | Interactive shell with build environment |
| `spc dev:is-installed` | Check whether a package is installed |
| `spc dev:dump-stages` | Dump all package build stages to JSON |
| `spc dev:dump-capabilities` | Dump buildable/installable capabilities |
| `spc dev:info` | Show configuration info for a package |

## `craft.yml` Changes

### Removed: `build-options.with-added-patch`

The `with-added-patch` key inside `build-options` is no longer parsed and will be silently ignored. Remove it from your `craft.yml`:

```yaml
# v2 — remove this block
build-options:
  with-added-patch:
    - my-patch.php
```

### `libs` → `packages` (both work)

The top-level `libs` field still works. The preferred v3 field name is `packages`, which is a superset covering libraries and other tool packages:

```yaml
# v2
libs: nghttp2,liblz4

# v3 (preferred)
packages: nghttp2,liblz4
```

## `env.ini` Variable Renames

If you customise `config/env.ini` or export environment variables in CI, update the following names:

| v2 Variable | v3 Variable |
|---|---|
| `SPC_LINUX_DEFAULT_CC` | `SPC_DEFAULT_CC` |
| `SPC_LINUX_DEFAULT_CXX` | `SPC_DEFAULT_CXX` |
| `SPC_LINUX_DEFAULT_AR` | `SPC_DEFAULT_AR` |
| `SPC_LINUX_DEFAULT_LD` | `SPC_DEFAULT_LD` |
| `SPC_LIBC` | `SPC_TARGET` |

`SPC_TARGET` uses a new format that encodes both architecture and libc in a single string, for example:

| v2 | v3 |
|---|---|
| `SPC_LIBC=musl` | `SPC_TARGET=x86_64-linux-musl` |
| `SPC_LIBC=gnu` | `SPC_TARGET=x86_64-linux-gnu.2.17` |

New logging variables were also added (`SPC_ENABLE_LOG_FILE`, `SPC_LOGS_DIR`, `SPC_PRESERVE_LOGS`). Refer to [Environment Variables](/en/guide/env-vars) for details.
