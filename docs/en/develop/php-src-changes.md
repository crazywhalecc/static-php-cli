# PHP Source Modifications

StaticPHP modifies the extracted PHP source tree to support static linking, additional SAPIs, older PHP branches, and platform-specific toolchains. These changes are applied to the generated workspace under `source/php-src`; the downloaded archive in `downloads/` is not modified.

This page describes the patches shipped by the `core` registry. It is an implementation inventory for maintainers, not a promise that `SourcePatcher`, lifecycle callback signatures, or individual patch names are stable public APIs.

## When Modifications Are Applied

Changes enter the source tree at several points:

1. **After `php-src` extraction**: artifact hooks apply compatibility changes before any package is built.
2. **Before `buildconf` / configure**: the PHP target applies the versioned patch set and rewrites build-system inputs.
3. **Before an individual SAPI or extension build**: conditional hooks patch generated Makefiles or extension sources.
4. **Temporarily around a stage**: a small number of changes are backed up and restored after that SAPI has been built.

Methods annotated with `#[PatchDescription]` emit a `[PATCH]` message when StaticPHP invokes them through its lifecycle/DI dispatcher. This is useful for tracing a build, but it is not a complete machine-readable manifest: direct method calls do not pass through that logger, and a few source rewrites and generated-file changes are not annotated.

## Changes Applied After Extraction

`src/Package/Artifact/php_src.php` runs whenever a fresh `php-src` artifact is extracted.

| Source or condition | Modification |
|---|---|
| PHP older than 8.0 | Applies the legacy stream-cast compatibility patch used by older Alpine/musl builds |
| PHP 8.0.x | Applies the libxml2 2.12 compatibility patch to DOM, libxml, and SOAP, plus the legacy stream-cast patch |
| PHP 8.1.x | Applies the legacy stream-cast patch |
| GD on all supported PHP branches | Replaces `ext/gd/config.w32` with StaticPHP's version-specific static-build configuration; PHP older than 8.2 also receives the `gdft.c` `_WIN32` compatibility fix |
| Bundled IMAP source without a license file | Adds the bundled Apache license text as `ext/imap/LICENSE` for license collection |

The GD and IMAP operations edit files that only matter when those extensions are present or built. The extraction hooks themselves run as part of extracting PHP source, before extension resolution reaches the build stage.

## Versioned PHP Patch Set

Before `buildconf`, both Unix and Windows builds call `SourcePatcher::patchPhpSrc()`. Despite its historical “micro patches” name, this happens for the PHP build in general; selecting `php-micro` is not required.

The default `SPC_MICRO_PATCHES` values from `config/env.ini` are:

| Host platform | Default patch names |
|---|---|
| Linux | `cli_checks`, `disable_huge_page` |
| macOS | `cli_checks`, `macos_iconv` |
| Windows | `static_extensions_win32`, `cli_checks`, `disable_huge_page`, `vcruntime140`, `win32`, `zend_stream`, `cli_static`, `win32_api` |

Their current purposes are:

| Patch | Purpose |
|---|---|
| `cli_checks` | Treat the micro SAPI like CLI in PHP code paths that otherwise compare the SAPI name with `cli` |
| `disable_huge_page` | Disable the Linux 2 MiB segment-alignment probe, avoiding unnecessary growth of SFX binaries |
| `macos_iconv` | Make the PHP iconv check link with the macOS iconv library correctly |
| `static_extensions_win32` | Adjust Windows extension configuration, including Fileinfo and OpenSSL, for static builds |
| `cli_static` | Adjust the Windows CLI source for a fully static executable |
| `vcruntime140` | Bypass the startup check for a dynamically loaded `vcruntime140` module |
| `win32` | Switch the PHP Windows build configuration from the dynamic MSVC runtime (`/MD`) to the static runtime (`/MT`) |
| `zend_stream` | Avoid a conflicting `isatty` declaration with the static Windows CRT |
| `win32_api` | Correct Windows API declarations needed by supported PHP branches |

Patch files live in `src/globals/patch/php-src-patches/`. A name may have an unversioned file such as `zend_stream.patch`, or versioned files such as `cli_checks_84.patch`. For a versioned name, StaticPHP chooses the newest available PHP minor less than or equal to the source minor. For example, an 8.2 source uses an `_81` patch when `_81` and `_84` are available but `_82` is not. PHP 7.4 skips this patch-set loader.

The directory also contains non-default or conditionally selected patches such as `comctl32`, `phar`, and `static_opcache`. A missing compatible patch or a failed `patch -p1` application stops the build with `PatchException`.

## Common Build-Time Changes

The PHP target applies two changes independently of the platform-specific tables below.

### StaticPHP Version Metadata

Before the PHP `build` stage, StaticPHP adds an INI entry named `StaticPHP.version` to `main/main.c` when the source does not already contain it. This makes the StaticPHP version that produced the binary available at runtime.

### Hardcoded INI Values

Each `--with-hardcoded-ini=key=value` option adds a literal line to `HARDCODED_INI` in every available supported SAPI source:

- `sapi/cli/php_cli.c`
- `sapi/micro/php_micro.c`
- `sapi/embed/php_embed.c`

The original files are saved with a `.bak` suffix. When a later invocation supplies another non-empty set, StaticPHP restores the backups before applying that set, so replacing one configured set with another does not accumulate old values. Completely omitting all `--with-hardcoded-ini` options currently does not run the restore path; re-extract `php-src` to remove previously injected values.

::: warning
Hardcoded values become part of the source and resulting binary. Do not use this option for passwords, tokens, or other secrets.
:::

## Unix Build-Time Changes

Linux and macOS builds apply the following target-aware changes:

| Condition | File or generated input | Modification |
|---|---|---|
| Every Unix PHP build | `configure.ac` | Replaces host `ldd`-based musl detection with the libc selected by `SPC_TARGET`; the original file is backed up |
| Every Unix PHP build | `build/php.m4` and extension `config.m4` files | Replaces `PKG_CHECK_MODULES` with `PKG_CHECK_MODULES_STATIC` so dependency checks include private static link requirements |
| PHP 8.3.x | `build/php.m4` | Applies the AVX-512 configure-cache compatibility patch used before PHP 8.4 |
| PHP older than 8.3 | generated `configure` | Uses the supported `-std=gnu17` probe instead of the newer `-std=gnu23` probe |
| Dynamically linked musl target | `TSRM/TSRM.h` | Gives the main TSRM TLS cache symbol default visibility so shared extensions using the `initial-exec` TLS model can load |
| Linux | generated `Makefile` | Normalizes accidental `//lib` paths to `/lib` |
| Linux with Zig | generated `Makefile` | Uses the host `cc` for `BUILD_CC` instead of `zig-cc`, preventing host tools such as minilua from being built for the target environment |
| A release linker flag is present | `ext/standard/info.c` | Hides the configure command from PHP information output |

When `php-micro` is resolved, its package hooks also adjust the generated Makefile so the embed prerequisite builds only `libphp.la` and the main PHP install step does not install the micro binary prematurely.

If `ext-phar` is selected for a Unix micro build, `ext/phar/phar.c` is temporarily changed so a compressed PHAR appended to the current executable can be recognized even when the executable name does not contain `.phar`. The original file is restored immediately after the micro stage.

## Windows Build-Time Changes

Windows requires more extensive build-system changes because PHP's upstream Windows flow normally assumes a dynamic CRT, PHP SDK tools, and import-library-based SAPIs.

| Stage or condition | Modification |
|---|---|
| Before `buildconf.bat` | Removes the shared `dllmain.c` object from the relevant Windows core build rule, then applies the versioned patch set |
| Missing `win32/wsyslog.h` | Generates a compatibility header with the event IDs defined by `win32/build/wsyslog.mc` |
| PHP 8.1.x | Moves the Fiber assembly objects from linker flags to the assembly object list so they participate in static linking |
| Detected Visual Studio | Rewrites `win32/build/confutils.js` so supported Visual Studio installations report the correct PHP toolset name |
| After `buildconf.bat` | Disables the generated `configure.js` PHP SDK version check because v3 uses MSVC plus its managed MSYS2 environment instead of PHP SDK binary tools |
| `--enable-micro-win32` | Defines `PHP_MICRO_WIN32_NO_CONSOLE` in `sapi/micro/php_micro.c`; a backup is restored when the option is no longer active |
| CLI build | Rewrites the generated `php.exe` rule to link PHP core, the CLI SAPI, static extensions, assembly objects, and static libraries directly; also places `buildroot/include` flags before extension flags |
| CGI build | Rewrites the generated `php-cgi.exe` rule for static linking and removes the duplicate `ZEND_TSRMLS_CACHE_DEFINE()` from the CGI source |
| Micro build | Adds the Fiber assembly prerequisites needed by the generated micro target and supplies the static library set |
| Embed build | Rewrites the generated embed target so `phpNembed.lib` (for example, `php8embed.lib`) contains the embed SAPI, PHP core, static extensions, and assembly objects instead of being only an import library |

The final embed change is also what makes the Windows FrankenPHP build possible: FrankenPHP links the self-contained embed library through CGO with Clang/LLD. Unix FrankenPHP does not apply a separate php-src patch; it consumes the Unix `php-embed` library produced by the normal PHP target.

## Conditional Extension Patches

Extension package classes may modify their source under `php-src/ext/<name>` when that extension is resolved. Two cross-cutting examples are:

- **Opcache**: `ext-opcache` applies a version-specific static Opcache patch for PHP 8.0–8.4.x. It uses dedicated legacy patches before PHP 8.2.23 and PHP 8.3.11, and otherwise uses the versioned `static_opcache` patch. PHP 8.5 and later support the required static path without this patch.
- **PHAR for micro**: the Unix micro hook described above is applied only when `ext-phar` participates in the build and is reversed after the micro binary is produced.

Other extension classes contain narrower compatibility changes for particular PHP, compiler, dependency-library, or operating-system versions. They are deliberately kept next to that extension's build logic in `src/Package/Extension/`, rather than added to the global PHP patch set. Search for `#[PatchDescription]` to review the current annotated inventory.

## Patch Application and Recovery

`SourcePatcher::patchFile()` resolves relative patch names under `src/globals/patch/`, checks whether the patch is already applied with a reverse dry run, and then invokes `patch --binary -p1`. This makes file-based patches safe to encounter again in the same extracted tree. Direct string replacements are written so repeated invocation normally finds no original text to replace.

There is no transaction that restores the whole PHP tree after a build. Only explicitly temporary changes, such as the PHAR and hardcoded-INI backups, have dedicated restoration logic. To return to pristine upstream source, remove/re-extract `source/php-src`, or use `switch-php-version` without `--keep-source` when changing or refreshing the PHP version.

The v2 arbitrary patch-point injection mechanism is not a supported v3 interface. Until a public patch extension contract is defined, new core patches should be implemented as maintainer-owned artifact or package hooks.

## Adding or Updating a Core Patch

When maintaining a built-in patch:

1. Prefer an upstream fix; keep a downstream patch only while supported PHP branches still need it.
2. Put reusable patch files in `src/globals/patch/`; put the version-selected micro/static-PHP set in `src/globals/patch/php-src-patches/`.
3. Use an exact PHP/platform/dependency condition so unrelated builds do not mutate their sources unnecessarily.
4. Make the operation repeatable and fail loudly when the expected upstream context is no longer present.
5. Add `#[PatchDescription]` to a method invoked through the lifecycle/DI dispatcher so the change is visible in build logs; log direct calls explicitly.
6. Test the oldest and newest PHP branches affected by the patch, and update both language versions of this page when behavior changes.
