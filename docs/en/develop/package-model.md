# Package Model

## Package Definition

A Package is the core concept in StaticPHP's build system, representing a buildable/installable unit such as a PHP extension, library, or build target.

Each Package contains build information, dependencies, and build logic, forming StaticPHP's build model. Package definitions are primarily implemented through YAML/JSON configuration files. The package configuration files for the `core` registry are located in the `config/pkg/` directory, and the corresponding build classes are in the `src/Package/` directory.

Packages are primarily divided into four types:

- **php-extension**: A PHP extension package containing build information and logic for a PHP extension.
- **library**: A library package containing build information and logic for build tools, dependency libraries, etc.
- **target**: A build target package representing the final build artifact, such as a PHP binary or curl binary. Inherits from the `library` package type.
- **virtual-target**: A virtual build target package representing an abstract build target that doesn't directly correspond to a build artifact, primarily used for dependency management and build scheduling.

```yaml
{pkg-name}:
  type: {pkg-type}
  ...
```

## Artifact Definition

An Artifact is a definition independent of Packages. It contains the source archive file or pre-built binary for building packages. Each Artifact defines download URLs, extraction methods, and build artifact file paths. Packages can reference one or more Artifacts via the `artifact` field to obtain the source or binaries needed for building.

In simple terms, by default one Package corresponds to one Artifact; if multiple Packages share the same source, you can define a single Artifact for multiple Packages to reference. Artifact definitions are located in the `config/artifact/` directory, and the corresponding custom download/extract logic classes are in the `src/Package/Artifact/` directory. For special package types like virtual targets and PHP built-in extensions, a Package may also omit the Artifact field entirely.

Assuming `example-library-package` is a dependency library whose source archive is hosted at `https://example.com/example-library.tar.gz`, its Package and Artifact definitions would look like this:

```yaml
example-library-package:
  type: library
  artifact:
    source:
      type: url
      url: 'https://example.com/example-library.tar.gz'
```

For more on Artifact definitions, see the [Artifact Model](./artifact-model) chapter.

## php-extension Package Type

A php-extension package represents a PHP extension. Its configuration file is located in the `config/pkg/ext/` directory, and its build class inherits from `PhpExtensionPackage` in the `src/Package/Extension/` directory. PHP extension package configurations include extension name, version, dependencies, build options, and more.

```yaml
ext-lz4:
  type: php-extension
  artifact:
    source:
      type: git
      url: 'https://github.com/kjdev/php-ext-lz4.git'
      rev: master
      extract: php-src/ext/lz4
    metadata:
      license-files: [LICENSE]
      license: MIT
  depends:
    - liblz4
  php-extension:
    arg-type@unix: '--enable-lz4=@shared_suffix@ --with-lz4-includedir=@build_root_path@'
    arg-type@windows: '--enable-lz4'
```

Allowed fields for `php-extension`:

```yaml
ext-{ext-name}:          # Package name must start with ext- prefix
  type: php-extension

  # ── Common Fields ────────────────────────────────────────────────────────
  description: '..'       # Optional, human-readable package description
  lang: c                 # Optional, implementation language of the extension (c / c++ etc.)
  frameworks: []          # Optional, list of related macOS framework dependencies

  artifact: '{artifact-name}'  # Optional; when a string, references an Artifact definition
                               # with the same name; when an object, is an inline Artifact
                               # (built-in extensions don't need this field)

  # depends / suggests support @windows / @unix / @linux / @macos suffixes
  depends: []             # Optional, hard dependency list (library names as-is, PHP extensions need ext- prefix)
  depends@unix: []        # Optional, hard dependencies only effective on Unix platforms
  depends@windows: []     # Optional, hard dependencies only effective on Windows platforms
  suggests: []            # Optional, optional dependency list (same format as depends)
  suggests@unix: []

  # ── php-extension Specific Fields (nested under php-extension: object) ────
  php-extension:
    # arg-type determines the form of arguments passed to ./configure, supports platform suffixes
    # Supported platform suffixes: @unix (Linux + macOS), @linux, @macos, @windows
    # Priority (using Linux as example): arg-type@linux > arg-type@unix > arg-type (no suffix)
    # Built-in keywords:
    #   enable      → --enable-{extname} (default value, used when not configured)
    #   enable-path → --enable-{extname}={buildroot}
    #   with        → --with-{extname}
    #   with-path   → --with-{extname}={buildroot}
    #   custom/none → Pass no arguments (handled by the #[CustomPhpConfigureArg] method in the PHP class)
    # You can also write the full argument string directly, supporting the following placeholders:
    #   @build_root_path@      → BUILD_ROOT_PATH (absolute path of buildroot)
    #   @shared_suffix@        → Expands to =shared in shared builds, empty in static builds
    #   @shared_path_suffix@   → Expands to =shared,{buildroot} in shared builds,
    #                            expands to ={buildroot} in static builds
    arg-type: enable
    arg-type@unix: '--enable-{extname}=@shared_suffix@'
    arg-type@windows: with-path

    zend-extension: false   # Optional, true indicates this is a Zend extension (e.g., opcache, xdebug)
    build-shared: true      # Optional, whether building as a shared extension (.so) is allowed, default true
    build-static: true      # Optional, whether inline static building (compiled into PHP) is allowed, default true
    build-with-php: true    # Optional, true means the extension is built together via the PHP source tree
                            # (used for built-in extensions)

    # display-name affects the php --ri argument in smoke tests and the license export display name
    # If not set, defaults to the extension name (the part after ext-); if set to empty string, skips --ri check
    display-name: 'My Extension'

    # os restricts the extension to be available only on specified platforms;
    # platforms not in the list will be rejected for building
    # Allowed values: Linux, Darwin, Windows
    os: [Linux, Darwin]
```

## library Package Type

A library package represents a dependency library that needs to be compiled from source (such as openssl, zlib, etc.). Its configuration file is located in the `config/pkg/lib/` directory, and its build class inherits from `LibraryPackage` in the `src/Package/Library/` directory.

Taking openssl as an example:

```yaml
openssl:
  type: library
  artifact:
    source:
      type: ghrel
      repo: openssl/openssl
      match: openssl.+\.tar\.gz
      prefer-stable: true
    binary: hosted
    metadata:
      license-files: [LICENSE.txt]
      license: OpenSSL
  depends:
    - zlib
  depends@windows:
    - zlib
    - jom
  headers:
    - openssl
  static-libs@unix:
    - libssl.a
    - libcrypto.a
  static-libs@windows:
    - libssl.lib
    - libcrypto.lib
```

Allowed fields for `library`:

```yaml
{lib-name}:
  type: library           # library or target (target inherits all fields from library)

  # ── Common Fields ─────────────────────────────────────────────────────────
  description: '..'       # Optional, human-readable package description
  license: MIT            # Optional, SPDX license identifier (for license export)
  lang: c                 # Optional, implementation language of the library (c / c++ etc.)
  frameworks: []          # Optional, list of related framework tags

  artifact: '{artifact-name}'  # Required; when a string, references an Artifact definition
                               # with the same name; when an object, is an inline Artifact

  # depends / suggests support @windows / @unix / @linux / @macos suffixes
  depends: []             # Optional, hard dependency list (library names or PHP extension names with ext- prefix)
  depends@unix: []
  depends@windows: []
  suggests: []            # Optional, optional dependency list (same format as depends)

  # ── library / target Specific Fields ───────────────────────────────────────
  # The following fields are used to verify that artifacts have been correctly
  # installed after the build. They support @unix / @windows / @linux / @macos suffixes.

  # Verify that specified header files or directories exist under buildroot/include/
  # Relative paths are based on buildroot/include/, absolute paths are used directly
  headers:
    - openssl             # Corresponds to buildroot/include/openssl/
    - zlib.h              # Corresponds to buildroot/include/zlib.h
  headers@unix:
    - ffi.h

  # Verify that specified static library files exist under buildroot/lib/
  # Relative paths are based on buildroot/lib/, absolute paths are used directly
  static-libs@unix:
    - libssl.a
  static-libs@windows:
    - libssl.lib

  # Verify that specified .pc files exist under buildroot/lib/pkgconfig/
  # Only checked on non-Windows platforms (pkg-config is not applicable on Windows)
  pkg-configs:
    - openssl             # Corresponds to buildroot/lib/pkgconfig/openssl.pc
    - libssl              # Auto-completes .pc suffix

  # Verify that specified executable files exist under buildroot/bin/
  # Relative paths are based on buildroot/bin/, absolute paths are used directly
  static-bins:
    - my-tool

  # List of directories injected into the global PATH after the package is installed.
  # Path placeholders are supported (see below for details).
  path:
    - '{pkg_root_path}/rust/bin'

  # Environment variables set after the package is installed (overwrites existing values).
  # Path placeholders are supported.
  env:
    MY_VAR: '{build_root_path}/lib'

  # Values appended to the end of existing environment variables after the package is installed.
  # Path placeholders are supported.
  append-env:
    CFLAGS: ' -I{build_root_path}/include'
```

The following path placeholders are supported in string values of the `path`, `env`, and `append-env` fields:

| Placeholder | Actual Path |
|---|---|
| `{build_root_path}` | buildroot directory (`buildroot/`) |
| `{pkg_root_path}` | pkgroot directory (`pkgroot/`) |
| `{working_dir}` | Working directory (project root) |
| `{download_path}` | Download cache directory (`downloads/`) |
| `{source_path}` | Extracted source directory (`source/`) |
| `{spc_msys2_path}` | MSYS2 root directory (`msys64/`) — Windows only |

## target Package Type

A `target` package represents a final build artifact. It inherits from `library`, so it includes all definition fields of `library`. The configuration file for `target` packages is located in the `config/pkg/target/` directory, and its build class inherits from `TargetPackage` in the `src/Package/Target/` directory.

The only difference from `library` is that a `target` package can be registered as a build target and automatically registers the build command `spc build:{target-name}`.

## virtual-target Package Type

Unlike `target`, a `virtual-target` may not include an `artifact`, meaning it doesn't directly correspond to a buildable entity but is instead an abstract build target, primarily used for dependency management and build scheduling. The configuration file for `virtual-target` is located in the `config/pkg/target/` directory, and its build class inherits from `TargetPackage` in the `src/Package/Target/` directory. Its definition is essentially the same as `target`, but the `artifact` field is optional and typically not set. `virtual-target` is primarily used in the following scenarios:

- Defining an abstract build target for other packages to depend on, without directly corresponding to a buildable entity.
- Serving as a common dependency for multiple `target` packages, simplifying dependency management.

A typical example is the `php-cli`, `php-fpm` build targets for PHP. They have no independent source code and depend on `php-src`, with the final build outcome (CLI or FPM binary) determined through build scheduling.
