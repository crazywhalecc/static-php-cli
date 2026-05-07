# Project Structure

## Concepts

StaticPHP is a CLI application built on `symfony/console`, with core code located in the `src/StaticPHP` directory. It is organized into several modules:

- **Registry**: Manages registry data. Each registry contains multiple packages (Package), and the StaticPHP project ships with a built-in `core` registry that includes PHP and related extensions, dependencies, and more.
- **Package**: Represents a single package. There are four package types: `php-extension` (PHP extension), `library` (library), `target` (build target), and `virtual-target` (virtual build target). Each package contains build information, dependencies, and more.
- **Installer/Builder**: Handles installation and build logic for packages — executing build commands, extracting build artifacts, processing build results, etc.
- **Doctor**: Provides system environment checking, responsible for installing and verifying system-level dependencies such as `make`, `cmake`, `autoconf`, and more.
- **Runtime/Executor**: Contains runtime-related utility classes, such as shell command execution and CMake build execution.
- **Toolchain**: Provides toolchain abstraction interfaces for different operating systems and environments, handling system-level differences during the build process.
- **Utils**: General-purpose utility classes, such as file system operations, logging, and OS-specific helper methods.
- **DependencyResolver**: Resolves dependencies between packages and generates build order.

## Directory Layout

```
static-php-cli/
├── bin/                        # Executable entry scripts (spc, spc.ps1, setup-runtime, etc.)
├── config/
│   ├── env.ini                 # Default environment variable configuration
│   ├── env.custom.ini          # User-defined environment variables (overrides env.ini)
│   ├── artifact/               # Build artifact configuration (toolchain downloads, pre-built binaries, etc.)
│   └── pkg/                    # Package configuration files (YAML)
│       ├── ext/                # PHP extension package config (ext-*.yml, builtin-extensions.yml)
│       ├── lib/                # Library package config (*.yml)
│       └── target/             # Build target config (php.yml, curl.yml, etc.)
├── src/
│   ├── bootstrap.php           # Application bootstrap (auto-loading, DI container, etc.)
│   ├── globals/                # Global helper functions
│   ├── Package/                # Build logic implementations for each package (PHP classes)
│   │   ├── Artifact/           # Custom download/extract logic for build artifacts
│   │   ├── Command/            # Package-level custom commands
│   │   ├── Extension/          # PHP extension build classes (ext-*.php)
│   │   ├── Library/            # Library build classes (*.php)
│   │   └── Target/             # Build target classes (php.php, curl.php, etc.)
│   └── StaticPHP/              # Framework core code
│       ├── ConsoleApplication.php  # Symfony Console application entry
│       ├── Artifact/           # Build artifact download and extraction (Downloader, Extractor, etc.)
│       ├── Attribute/          # PHP attribute definitions
│       │   ├── Artifact/       # Artifact-related attributes (CustomSource, BinaryExtract, etc.)
│       │   ├── Doctor/         # Doctor-related attributes (CheckItem, FixItem, etc.)
│       │   └── Package/        # Package build-related attributes (BuildFor, BeforeStage, AfterStage,
│       │                       #   CustomPhpConfigureArg, PatchBeforeBuild, etc.)
│       ├── Command/            # CLI command implementations (build-libs, build-target, doctor, etc.)
│       ├── Config/             # Configuration loading and validation (PackageConfig, ArtifactConfig, etc.)
│       ├── DI/                 # Dependency injection container (ApplicationContext, CallbackInvoker, etc.)
│       ├── Doctor/             # System environment checking and fixing (Doctor, CheckResult)
│       ├── Exception/          # Custom exception classes
│       ├── Package/            # Core package models and build scheduling
│       │   ├── Package.php             # Base package class
│       │   ├── LibraryPackage.php      # Library package type
│       │   ├── PhpExtensionPackage.php # PHP extension package type
│       │   ├── TargetPackage.php       # Build target package type
│       │   ├── PackageInstaller.php    # Package installer (download, extract source)
│       │   └── PackageBuilder.php      # Package builder (execute build pipeline)
│       ├── Registry/           # Registry management (Registry, PackageLoader, ArtifactLoader)
│       ├── Runtime/            # Runtime utilities
│       │   ├── Executor/       # Command executors (UnixAutoconfExecutor, UnixCMakeExecutor,
│       │   │                   #   WindowsCMakeExecutor, Executor base class)
│       │   ├── Shell/          # Shell abstraction (UnixShell, WindowsCmd, etc.)
│       │   └── SystemTarget.php # System target information
│       ├── Toolchain/          # Toolchain abstraction (GccNative, Musl, MSVC, Zig, ClangBrew, etc.)
│       └── Util/               # General utility classes
│           ├── System/         # OS platform utilities (LinuxUtil, MacOSUtil, WindowsUtil, etc.)
│           ├── BuildRootTracker.php  # buildroot file tracking
│           ├── DependencyResolver.php # Dependency resolution and build order
│           ├── FileSystem.php        # File system operations
│           ├── GlobalEnvManager.php  # Global environment variable management
│           ├── InteractiveTerm.php   # Interactive terminal output
│           ├── LicenseDumper.php     # License export
│           ├── PkgConfigUtil.php     # pkg-config utility wrapper
│           ├── SourcePatcher.php     # Source code patching utility
│           └── SPCConfigUtil.php     # SPC configuration reader
├── tests/                      # Unit tests and integration tests
├── downloads/                  # Download cache directory (source packages, pre-built binaries)
├── source/                     # Extracted source code directory
├── buildroot/                  # Build output directory (headers, static libraries, etc.)
├── pkgroot/                    # Platform-archived build artifacts
└── spc.registry.yml            # core registry definition file
```

Note that the classes in `src/Package` are responsible for implementing the build logic of specific packages, while the classes in `src/StaticPHP` provide the core functionality of the build framework, such as command scheduling, environment checking, and toolchain abstraction. The two are decoupled: `src/Package` corresponds to the packages in the `core` registry (including PHP, extensions, libraries, and build targets), while `src/StaticPHP` is the infrastructure that supports build needs across different registries and packages.
