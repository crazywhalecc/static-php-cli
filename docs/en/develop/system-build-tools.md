# Compilation Tools

StaticPHP builds PHP, libraries, extensions, and helper tools by invoking native build systems. The environment is split into three layers:

1. **Host tools** such as `autoconf`, `cmake`, `make`, `patch`, and parsers/code generators.
2. **Compiler toolchain** such as Zig, GCC, Clang, or MSVC. The selected toolchain provides the compiler, linker, archiver, and target-specific environment.
3. **Package build executors** that apply StaticPHP's common Autoconf or CMake arguments and run the package build.

This distinction matters when troubleshooting: having `cmake` installed does not mean that the selected compiler toolchain is initialized, and having a compiler does not mean that all source-generation tools are present.

## Checking the Environment

Run `doctor` after installing StaticPHP and after changing the build target or toolchain:

```bash
# Pre-built binary
./spc doctor

# Source installation
bin/spc doctor
```

To let StaticPHP install supported missing items, use:

```bash
./spc doctor --auto-fix
```

On Linux and macOS, automatic fixes may invoke the system package manager and may require `sudo`. On Windows, StaticPHP can install its own helper packages, but it cannot install Visual Studio or Git for you. See the [`doctor` command reference](/en/guide/cli-reference#doctor) for its command-line options.

Doctor checks are target-aware. For example, Zig and musl-related checks only run when the selected toolchain and target require them, and a Homebrew or MacPorts LLVM check only runs when that LLVM variant is selected.

## What the Tools Are Used For

| Tool group | Examples | Role in a build |
|---|---|---|
| Compiler and binutils | `zig`, `gcc`, `g++`, `clang`, `cl.exe`, `ld`, `link.exe`, `ar`, `lib.exe`, `ranlib` | Compile and link PHP and dependency libraries |
| Autotools | `autoconf`, `automake`, `libtoolize` / `glibtoolize`, `autopoint` | Generate `configure` scripts and supporting files |
| Make tools | `make`, `nmake.exe` | Execute generated Makefiles |
| CMake | `cmake` | Configure and build CMake-based packages |
| Source generators | `bison`, `re2c`, `flex`, `gperf` | Regenerate parsers, lexers, and generated C/C++ sources |
| Dependency metadata | `pkg-config` | Read `.pc` files and return static compile/link flags |
| Source and archive tools | `git`, `patch`, `tar`, `unzip`, `gzip`, `bzip2`, `xz`, `7za.exe` | Fetch, patch, and extract source archives |
| Package-specific tools | `nasm`, Perl, Go | Build packages such as OpenSSL or FrankenPHP when selected |

Not every package uses every tool. Doctor verifies the common host baseline and target-aware prerequisites; an individual package may also resolve a tool package such as Go, NASM, or Perl when selected.

## Linux

`LinuxToolCheck` detects the distribution from `/etc/os-release`, selects a package-family baseline, and checks the command (or file) provided by each package. Its current baselines are:

| Distribution family | Checked packages or commands |
|---|---|
| Alpine | `make`, `bison`, `re2c`, `flex`, `gperf`, `git`, `autoconf`, `automake`, `gettext-dev`, `tar`, `unzip`, `gzip`, `bzip2`, `cmake`, `gcc`, `g++`, `patch`, `binutils-gold`, `libtoolize`, `which` |
| Debian / Ubuntu and the fallback baseline | `make`, `bison`, `re2c`, `flex`, `gperf`, `git`, `autoconf`, `automake`, `autopoint`, `tar`, `unzip`, `gzip`, `gcc`, `g++`, `bzip2`, `cmake`, `patch`, `xz`, `libtoolize`, `which` |
| RHEL / Fedora | `perl`, `make`, `bison`, `re2c`, `flex`, `gperf`, `git`, `autoconf`, `automake`, `tar`, `unzip`, `gzip`, `gcc`, `g++`, `bzip2`, `cmake`, `patch`, `which`, `xz`, `libtool`, `gettext-devel`, `file` |
| CentOS | The RHEL baseline plus the Perl `IPC::Cmd` and `Time::Piece` modules |
| Arch / Manjaro | `base-devel`, `cmake`, `gperf` |

Doctor additionally checks:

- CMake is at least version 3.22.
- `re2c` is at least version 1.0.3.
- `linux-headers` is available on a musl distribution.
- StaticPHP's isolated `pkg-config` is installed and executable.

The Linux default is `ZigToolchain`. The toolchain declares the `zig` tool package, so every build installs it even when `spc doctor` was never run (Doctor can also install it when it is missing, and `--auto-fix` checks the rest of the environment). It also declares `llvm-compiler-rt`, which every build installs before anything else: it supplies the profile runtime, the `crtbegin`/`crtend` objects and `libclang_rt.cpu_model.a` that the zig-shipped Clang omits. Its sources come from a trimmed compiler-rt archive published under `dl.static-php.dev/v3/deps/compiler-rt/`, keyed by the clang version zig reports, so a build downloads about 140KB instead of the 150MB+ llvm-project tarball that LLVM has required since release 22. That archive is the only source: when the archive for the zig version in use is missing, the build fails with instructions for publishing it. Because compiler-rt sources are resolved from the clang zig ships, a machine that has no zig yet installs zig on its first build and picks compiler-rt up on the next one. The objects are compiled locally (a few seconds) and are rebuilt when zig is upgraded or when `SPC_TARGET` changes the target triple. When a musl target is built on a non-musl host, Doctor also checks the musl runtime/wrapper required by that target. The legacy `MuslToolchain` additionally expects the musl cross toolchain under `/usr/local/musl`. A native Alpine build does not need these cross-host components.

Under `ZigToolchain`, `llvm-objcopy`, `llvm-strip` and `llvm-profdata` come from the `llvm-tools` package. Cross builds, where `SPC_TARGET` names a different architecture or OS than the host, require it, because the host binutils only understand the host architecture: the toolchain then declares `llvm-tools` alongside `llvm-compiler-rt`, and the build installs it automatically. Native builds keep using the host binutils (zig's own `objcopy` only implements a subset of the options), and a failing strip or debug-info split only produces a warning instead of aborting the build; run `bin/spc install-pkg llvm-tools` to use the LLVM tools there as well. The package downloads prebuilt static binaries from `dl.static-php.dev/v3/deps/llvm-tools/`, selected by the host architecture (not `SPC_TARGET`) and keyed by the clang version zig reports, and verifies them against the published SHA-256 checksum. When no archive is published for the zig version in use yet (or the checksum does not match), it falls back to building LLVM from source, which takes much longer. Like compiler-rt, it needs zig on disk to know that version, so a cross build on a machine without zig installs zig first and picks up `llvm-tools` on the next run.

::: tip
The target string and the host distribution are different concepts. A glibc distribution can produce a musl target through Zig, while a `*-gnu.*` target remains dynamically linked to the requested glibc ABI.
:::

## macOS

The default compiler toolchain is the system Clang supplied by Xcode or Xcode Command Line Tools. StaticPHP also requires either Homebrew or MacPorts to provide the remaining build tools.

Doctor currently checks these commands:

```text
curl, make, bison, re2c, flex, gperf, pkg-config, git,
autoconf, automake, tar, libtool, unzip, xz, gzip, bzip2,
cmake, glibtoolize
```

It also requires GNU Bison 3 or later. If `/usr/bin/bison` is too old, Doctor searches the Homebrew and MacPorts locations and can install a newer package. On Apple Silicon, a Homebrew installation used by StaticPHP must be the native installation under `/opt/homebrew`, not an Intel installation under `/usr/local`.

`SPC_USE_LLVM` controls the Clang variant:

| Value | Toolchain | Compiler location |
|---|---|---|
| `system` (default) | `ClangNativeToolchain` | Xcode / Command Line Tools `clang` |
| `brew` | `ClangBrewToolchain` | Homebrew `opt/llvm/bin` |
| `port` | `ClangPortsToolchain` | MacPorts `bin` (normally `/opt/local/bin`) |

When `brew` or `port` is selected, Doctor verifies that the corresponding LLVM installation contains `clang`.

## Windows

Windows builds currently target x86-64 and use two kinds of prerequisites.

The following components must be installed by the user:

- Visual Studio with the x86/x64 C++ tools component.
- Git for Windows, with `patch.exe` available on `PATH` (normally from `C:\Program Files\Git\usr\bin`).

Doctor can install these StaticPHP-managed baseline packages:

| Package | Purpose |
|---|---|
| `vswhere` | Locate the newest Visual Studio installation that contains `Microsoft.VisualStudio.Component.VC.Tools.x86.x64` |
| `msys2-build-essentials` | Provide MSYS2 plus `make`, Autotools, `libtool`, `pkgconf`, Perl, Bison, and `re2c` |
| `7za-win` | Extract formats that need `7za.exe` |

Additional tools such as `nasm` and `strawberry-perl` are also represented as Tool packages, but they are installed when a selected package declares them through `tools`; they are not unconditional Windows Doctor checks.

`MSVCToolchain` calls the detected Visual Studio `vcvarsall.bat x64` and imports its environment. Doctor then verifies `cl.exe`, `link.exe`, `lib.exe`, `dumpbin.exe`, `msbuild.exe`, and `nmake.exe`. The imported environment is cached in `downloads/.vcenv-cache` for up to one hour.

The MSYS2 root defaults to `pkgroot/.../msys2-build-essentials/msys64`. It can be changed with `SPC_MSYS2_PATH`; the value must point to the `msys64` directory. The old v2 PHP SDK directory and Visual Studio version options are not used in v3.

### LLVM/Clang for FrankenPHP

The normal Windows PHP and library build uses MSVC. FrankenPHP is the exception: its CGO link step requires Clang and LLD.

StaticPHP first accepts an absolute Clang path from `CC`. Otherwise it looks for the Visual Studio LLVM installation at `VC\Tools\Llvm\x64\bin\clang.exe` using `vswhere`. Install the **C++ Clang tools for Windows** component in Visual Studio Installer when building FrankenPHP. This Clang requirement does not replace the MSVC requirement for the PHP and dependency-library stages.

## Compiler Toolchain Selection

`GlobalEnvManager` loads `config/env.ini` (and an optional `config/env.custom.ini`) before `ToolchainManager` initializes the selected toolchain. The normal selection is:

| Host OS | Default | Other implementations currently present |
|---|---|---|
| Linux | `ZigToolchain` | `GccNativeToolchain`, `ClangNativeToolchain`, `MuslToolchain` |
| macOS | `ClangNativeToolchain` | `ClangBrewToolchain`, `ClangPortsToolchain` |
| Windows | `MSVCToolchain` | None |

On Unix, the toolchain supplies defaults for `CC`, `CXX`, `AR`, `RANLIB`, and `LD`. Values already set by the caller take precedence over defaults from `env.ini`. `SPC_TARGET` determines the target OS/libc/ABI, while `SPC_USE_LLVM` chooses the macOS Clang distribution. `SPC_TOOLCHAIN` can override the implementation class, but it is currently a maintainer-level/internal setting rather than a stable public extension contract.

After environment initialization, the toolchain validates its commands and target/libc combination. It also records a compiler description in `PHP_BUILD_COMPILER` when one can be determined.

## Isolated pkg-config

Unix builds deliberately use a StaticPHP-managed `pkg-config`, rather than whichever executable happens to be first on the system `PATH`. `PkgConfigUtil` searches, in order:

1. `pkgroot/<platform>/bin/pkg-config`
2. `buildroot/bin/pkg-config`

Doctor can install a pre-built copy through the `pkg-config` tool package. If a source build is needed, StaticPHP builds pkg-config with its internal GLib and disables the system include, library, sysroot, and default `.pc` search paths.

During a build, `PKG_CONFIG_PATH` starts with `buildroot/lib/pkgconfig`, and StaticPHP's dependency queries and generated CMake toolchain file request `--static` flags. This prevents an unrelated system library from silently satisfying a dependency that StaticPHP intended to build itself.

## Package Build Executors

Package classes use three common executor implementations:

| Executor | Platform and build system | Current behavior |
|---|---|---|
| `UnixAutoconfExecutor` | Linux/macOS, Autoconf | Adds static/PIC and `buildroot` prefix arguments, runs `configure`, parallel `make`, and optionally `make install` |
| `UnixCMakeExecutor` | Linux/macOS, CMake | Generates a toolchain file rooted at `buildroot`, pins StaticPHP's compilers and pkg-config, disables shared libraries, then configures, builds, and installs |
| `WindowsCMakeExecutor` | Windows, CMake/MSVC | Uses the x64 Visual Studio generator, the static MSVC runtime, a StaticPHP CMake toolchain file, and `cmake --build --target install` |

The executors initialize package-specific include, library, and environment paths and use the configured build concurrency. Unix Autoconf failures preserve `config.log`, and the Unix CMake executor copies available configure/error/output logs to `SPC_LOGS_DIR`. The Windows CMake executor currently relies on the general shell and output logs instead of collecting those CMake files separately.

These classes are useful landmarks when maintaining built-in packages, but their constructors and fluent methods are not yet declared a stable PHP API. Package authors should follow existing `src/Package/Library` or `src/Package/Tool` implementations until the public extension API is defined.

## Diagnosing Tool Failures

When Doctor passes but a package still fails:

1. Run `spc dev:shell` (or `bin/spc dev:shell`) and check `CC`, `CXX`, `AR`, `LD`, `PKG_CONFIG`, and `PATH`.
2. Confirm that `SPC_TARGET` describes the intended libc and ABI.
3. Read `log/spc.output.log`, `log/spc.shell.log`, and any package-specific Autoconf/CMake logs.
4. On Windows, re-run Doctor if Visual Studio, MSYS2, or LLVM components changed; remove the one-hour `downloads/.vcenv-cache` only when a stale Visual Studio environment is suspected.

Do not work around a missing tool by copying binaries into `buildroot/` or editing generated build directories. Add it as a host prerequisite, a StaticPHP tool package, or package build dependency according to its role.
