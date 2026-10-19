# StaticPHP Package Reference

## Contents

- Package locations
- Package types
- Artifact model
- Common YAML fields
- PHP extension fields
- Platform suffixes
- Validation and tests

## Package Locations

StaticPHP v3 keeps package metadata in YAML and behavior in PHP classes.

| Kind | YAML | PHP class namespace | Typical attribute |
|---|---|---|---|
| PHP extension | `config/pkg/ext/ext-name.yml` | `Package\Extension` | `#[Extension('name')]` |
| Library | `config/pkg/lib/name.yml` | `Package\Library` | `#[Library('name')]` |
| Target | `config/pkg/target/name.yml` | `Package\Target` | `#[Target('name')]` |
| Virtual target | `config/pkg/target/name.yml` | `Package\Target` | `#[Target('name')]` |
| Tool | `config/pkg/tool/name.yml` | `Package\Tool` | `#[Tool('name')]` |
| Custom artifact | `config/artifact/name.yml` | `Package\Artifact` | artifact attributes |

Prefer one package per YAML file unless an existing file groups related definitions. Match nearby naming and ordering.

## Package Types

`php-extension` packages describe PHP extensions. Config package names must use the `ext-` prefix. Dependencies on extensions also use `ext-`.

`library` packages describe buildable dependency libraries. They usually define source artifacts plus verification fields such as headers, static libraries, pkg-config files, or binaries.

`target` packages represent final build outputs and inherit the library-style fields. StaticPHP automatically exposes build commands for targets.

`virtual-target` packages are abstract dependency/build scheduling nodes. They may omit `artifact`.

`tool` packages are helper programs or toolchains installed into `pkgroot/` or used by builds.

## Artifact Model

Artifacts define downloadable source archives or prebuilt binaries. Use inline artifacts for simple one-package sources and `config/artifact/*.yml` for shared, complex, or custom artifacts.

Top-level artifact sections:

- `source`: source archive, git checkout, PECL/PIE package, local directory, or custom source.
- `binary`: prebuilt binary by platform, or aliases such as `hosted`/`custom`.
- `metadata`: `license`, `license-files`, and `source-root`.

Common source types:

- `url`: fixed URL; supports `filename`, `version`, and `extract`.
- `git`: repository source; use `rev` for a fixed branch/tag/commit or `regex` with named `version` capture for update checks.
- `ghrel`: GitHub release asset by regex filename match.
- `ghtar`: generated GitHub release tarball.
- `ghtagtar`: generated GitHub tag tarball.
- `filelist`: scrape a download index and extract version/filename with regex.
- `pecl`: PECL extension by name.
- `pie`: Packagist PIE extension by `vendor/package`.
- `php-release`: official PHP source selected by build PHP version.
- `local`: local source directory for development/offline use.
- `custom`: PHP artifact class handles source or binary logic.

Use `metadata.source-root` when the actual build directory is inside an extracted subdirectory.

Use `metadata.license-files` for source licenses. `@/file.txt` points to bundled license files in `src/globals/licenses/`.

## Common YAML Fields

Shared fields:

- `type`: required package type.
- `description`: optional human-readable package description.
- `license`: package-level license annotation where applicable.
- `lang`: implementation language such as `c` or `c++`.
- `frameworks`: macOS framework tags.
- `artifact`: string reference or inline artifact object. Required for `library` and `target`; optional for built-in extensions and virtual targets.
- `depends`: hard dependencies.
- `suggests`: optional dependencies.

Library, target, and tool verification/installation fields:

- `headers`: expected files/directories under `buildroot/include`.
- `static-libs`: expected static libraries under `buildroot/lib`.
- `pkg-configs`: expected `.pc` files under `buildroot/lib/pkgconfig`.
- `static-bins`: expected executables under `buildroot/bin`.
- `path`: directories appended to PATH after install.
- `env`: environment variables set after install.
- `append-env`: values appended to existing environment variables.

Path placeholders in `path`, `env`, and `append-env`:

- `{build_root_path}`: `buildroot/`
- `{pkg_root_path}`: `pkgroot/`
- `{working_dir}`: repository or working directory
- `{download_path}`: `downloads/`
- `{source_path}`: `source/`
- `{spc_msys2_path}`: MSYS2 root on Windows

## PHP Extension Fields

Extension-specific fields live under `php-extension`.

- `arg-type`: configure argument behavior. Built-ins include `enable`, `enable-path`, `with`, `with-path`, `custom`, and `none`. Full strings are allowed.
- `zend-extension`: true for Zend extensions such as opcache/xdebug.
- `build-shared`: whether shared builds are allowed.
- `build-static`: whether static builds are allowed.
- `build-with-php`: true when built inside the PHP source tree.
- `display-name`: value used for `php --ri` smoke tests and license display; empty string skips the `--ri` check.
- `os`: allowed OS families: `Linux`, `Darwin`, `Windows`.

Configure string placeholders:

- `@build_root_path@`: absolute buildroot path.
- `@shared_suffix@`: `=shared` in shared builds, empty in static builds.
- `@shared_path_suffix@`: `=shared,{buildroot}` in shared builds, `={buildroot}` in static builds.

## Platform Suffixes

Many fields support platform suffixes:

- `@unix`: Linux and macOS.
- `@linux`: Linux only.
- `@macos`: macOS only.
- `@windows`: Windows only.

For Linux, specific fields override generic fields in this order: `field@linux`, then `field@unix`, then `field`.

Use suffixes for declarative differences in dependencies, headers, libraries, pkg-configs, extension args, and suggestions.

## Validation and Tests

Run config validation for most package edits:

```bash
php bin/spc dev:lint-config
```

Run focused tests after config or registry changes:

```bash
vendor/bin/phpunit tests/StaticPHP/Config tests/StaticPHP/Registry tests/StaticPHP/Util/DependencyResolverTest.php --no-coverage
```

Run broader checks for framework or class changes:

```bash
composer test
composer analyse
```

Build commands are expensive. Use them for user-provided repros, package-specific fixes, or when validation cannot prove the behavior:

```bash
php bin/spc build:libs "openssl,curl" -vvv
php bin/spc build:php "bcmath,openssl,curl" --build-cli -vvv
```
