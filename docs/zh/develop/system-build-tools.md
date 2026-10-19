# 编译工具

StaticPHP 通过调用原生构建系统来构建 PHP、依赖库、扩展和辅助工具。整个环境可以分为三层：

1. **宿主机工具**，如 `autoconf`、`cmake`、`make`、`patch` 以及解析器/代码生成器。
2. **编译器工具链**，如 Zig、GCC、Clang 或 MSVC。选定的工具链负责提供编译器、链接器、归档器和目标平台环境。
3. **Package 构建 Executor**，负责应用 StaticPHP 通用的 Autoconf 或 CMake 参数并执行 Package 构建。

排查问题时需要区分这三层：安装了 `cmake` 不代表选定的编译器工具链已经初始化；存在编译器也不代表源码生成所需的全部工具已经齐备。

## 检查环境

安装 StaticPHP 后，以及修改构建目标或工具链后，都应运行 `doctor`：

```bash
# 预构建二进制
./spc doctor

# 源码安装
bin/spc doctor
```

如果允许 StaticPHP 自动安装可修复的缺失项，使用：

```bash
./spc doctor --auto-fix
```

在 Linux 和 macOS 上，自动修复可能调用系统包管理器，并可能需要 `sudo`。在 Windows 上，StaticPHP 可以安装自己管理的辅助 Package，但不能代替你安装 Visual Studio 或 Git。命令行选项参见 [`doctor` 命令参考](/zh/guide/cli-reference#doctor)。

Doctor 检查与当前所选目标相关。例如，只有选定的工具链和目标需要 Zig 或 musl 时，相关检查才会运行；只有选择对应 LLVM 变体时，才会检查 Homebrew 或 MacPorts LLVM。

## 各类工具的用途

| 工具类别 | 示例 | 在构建中的作用 |
|---|---|---|
| 编译器与 binutils | `zig`、`gcc`、`g++`、`clang`、`cl.exe`、`ld`、`link.exe`、`ar`、`lib.exe`、`ranlib` | 编译和链接 PHP 及依赖库 |
| Autotools | `autoconf`、`automake`、`libtoolize` / `glibtoolize`、`autopoint` | 生成 `configure` 脚本及其辅助文件 |
| Make 工具 | `make`、`nmake.exe` | 执行生成的 Makefile |
| CMake | `cmake` | 配置和构建基于 CMake 的 Package |
| 源码生成器 | `bison`、`re2c`、`flex`、`gperf` | 重新生成解析器、词法分析器和 C/C++ 源码 |
| 依赖元数据 | `pkg-config` | 读取 `.pc` 文件并返回静态编译/链接参数 |
| 源码与归档工具 | `git`、`patch`、`tar`、`unzip`、`gzip`、`bzip2`、`xz`、`7za.exe` | 获取、修补和解压源码归档 |
| Package 专用工具 | `nasm`、Perl、Go | 在选中相应 Package 时构建 OpenSSL 或 FrankenPHP 等项目 |

并非每个 Package 都会使用全部工具。Doctor 会检查通用宿主机基础环境和与目标相关的前置条件；选中具体 Package 时，还可能解析 Go、NASM 或 Perl 等额外工具 Package。

## Linux

`LinuxToolCheck` 从 `/etc/os-release` 识别发行版，选择对应发行版家族的基础清单，并检查每个包所提供的命令（或文件）。当前清单如下：

| 发行版家族 | 检查的包或命令 |
|---|---|
| Alpine | `make`、`bison`、`re2c`、`flex`、`gperf`、`git`、`autoconf`、`automake`、`gettext-dev`、`tar`、`unzip`、`gzip`、`bzip2`、`cmake`、`gcc`、`g++`、`patch`、`binutils-gold`、`libtoolize`、`which` |
| Debian / Ubuntu 及默认回退清单 | `make`、`bison`、`re2c`、`flex`、`gperf`、`git`、`autoconf`、`automake`、`autopoint`、`tar`、`unzip`、`gzip`、`gcc`、`g++`、`bzip2`、`cmake`、`patch`、`xz`、`libtoolize`、`which` |
| RHEL / Fedora | `perl`、`make`、`bison`、`re2c`、`flex`、`gperf`、`git`、`autoconf`、`automake`、`tar`、`unzip`、`gzip`、`gcc`、`g++`、`bzip2`、`cmake`、`patch`、`which`、`xz`、`libtool`、`gettext-devel`、`file` |
| CentOS | RHEL 清单外加 Perl 的 `IPC::Cmd` 和 `Time::Piece` 模块 |
| Arch / Manjaro | `base-devel`、`cmake`、`gperf` |

Doctor 还会检查：

- CMake 版本不低于 3.22。
- `re2c` 版本不低于 1.0.3。
- 在 musl 发行版上存在 `linux-headers`。
- StaticPHP 隔离使用的 `pkg-config` 已安装且可以执行。

Linux 默认使用 `ZigToolchain`。工具链会把 `zig` 工具 Package 声明为自己的依赖，因此即使从未运行过 `spc doctor`，每次构建也会自动安装它（Doctor 同样会在缺失时安装，`--auto-fix` 负责检查其余环境）。它还会声明 `llvm-compiler-rt`，每次构建都会先安装该 target：它提供 Zig 自带 Clang 所缺失的 profile 运行时、`crtbegin`/`crtend` 目标文件和 `libclang_rt.cpu_model.a`。其源码来自发布在 `dl.static-php.dev/v3/deps/compiler-rt/` 下的裁剪版 compiler-rt 归档，以 zig 报告的 clang 版本作为键，因此一次构建只需下载约 140KB，而不是 LLVM 自 22 版起才提供的 150MB+ llvm-project 单体包。该归档是唯一来源：若当前 zig 版本对应的归档尚未发布，构建会直接失败并提示如何发布它。由于 compiler-rt 的源码要按 zig 自带的 clang 版本来解析，尚无 zig 的机器会在第一次构建时装上 zig，并在下一次构建时补上 compiler-rt。这些对象在本地编译（只需几秒），并在 zig 升级或 `SPC_TARGET` 改变目标 triple 时重新构建。在非 musl 宿主机上构建 musl 目标时，Doctor 还会检查该目标所需的 musl 运行时/wrapper。旧式的 `MuslToolchain` 还要求 `/usr/local/musl` 下存在 musl 交叉工具链。原生 Alpine 构建不需要这些跨宿主机组件。

在 `ZigToolchain` 下，`llvm-objcopy`、`llvm-strip` 和 `llvm-profdata` 由 `llvm-tools` 包提供。交叉构建（`SPC_TARGET` 指定的架构或操作系统与宿主机不同）必须使用它，因为系统 binutils 只认识宿主机架构：此时工具链会在 `llvm-compiler-rt` 之外再声明 `llvm-tools`，构建过程会自动安装它。本机构建继续使用系统 binutils（Zig 自带的 `objcopy` 只实现了部分选项），strip 或拆分调试信息失败只会输出警告而不会中断构建；如需在本机构建中也使用 LLVM 工具，可运行 `bin/spc install-pkg llvm-tools`。该包会从 `dl.static-php.dev/v3/deps/llvm-tools/` 下载预构建的静态二进制：按宿主机架构（而非 `SPC_TARGET`）选择，以 zig 报告的 clang 版本作为键，并用发布的 SHA-256 校验值进行校验。若当前 zig 版本对应的归档尚未发布（或校验失败），则回退到从源码构建 LLVM，耗时会长得多。与 compiler-rt 一样，它需要磁盘上已有 zig 才能确定该版本，因此在尚无 zig 的机器上进行交叉构建时，会先安装 zig，并在下一次构建时补上 `llvm-tools`。

::: tip
目标字符串和宿主发行版是两个不同概念。glibc 发行版可以通过 Zig 生成 musl 目标，而 `*-gnu.*` 目标仍会动态链接到指定的 glibc ABI。
:::

## macOS

默认编译器工具链是 Xcode 或 Xcode Command Line Tools 提供的系统 Clang。StaticPHP 还要求安装 Homebrew 或 MacPorts 之一，以提供其余构建工具。

Doctor 当前检查以下命令：

```text
curl, make, bison, re2c, flex, gperf, pkg-config, git,
autoconf, automake, tar, libtool, unzip, xz, gzip, bzip2,
cmake, glibtoolize
```

它还要求 GNU Bison 3 或更高版本。如果 `/usr/bin/bison` 版本过旧，Doctor 会继续搜索 Homebrew 和 MacPorts 路径，并可安装较新的包。在 Apple Silicon 上，StaticPHP 使用的 Homebrew 必须是位于 `/opt/homebrew` 的原生安装，而不是 `/usr/local` 下的 Intel 安装。

`SPC_USE_LLVM` 控制使用的 Clang 变体：

| 值 | 工具链 | 编译器位置 |
|---|---|---|
| `system`（默认） | `ClangNativeToolchain` | Xcode / Command Line Tools 的 `clang` |
| `brew` | `ClangBrewToolchain` | Homebrew 的 `opt/llvm/bin` |
| `port` | `ClangPortsToolchain` | MacPorts 的 `bin`（通常为 `/opt/local/bin`） |

选择 `brew` 或 `port` 时，Doctor 会验证对应 LLVM 安装中是否包含 `clang`。

## Windows

Windows 当前以 x86-64 为构建目标，并使用两类前置依赖。

以下组件需要用户自行安装：

- Visual Studio，并勾选 x86/x64 C++ 工具组件。
- Git for Windows，且 `patch.exe` 可以从 `PATH` 找到（通常来自 `C:\Program Files\Git\usr\bin`）。

Doctor 可以安装以下由 StaticPHP 管理的基础 Package：

| Package | 用途 |
|---|---|
| `vswhere` | 查找包含 `Microsoft.VisualStudio.Component.VC.Tools.x86.x64` 的最新 Visual Studio 安装 |
| `msys2-build-essentials` | 提供 MSYS2，以及 `make`、Autotools、`libtool`、`pkgconf`、Perl、Bison 和 `re2c` |
| `7za-win` | 解压需要 `7za.exe` 的格式 |

`nasm`、`strawberry-perl` 等附加工具也已经表示为 Tool Package，但它们会在选中的 Package 通过 `tools` 声明时安装，并不是无条件运行的 Windows Doctor 检查。

`MSVCToolchain` 会调用检测到的 Visual Studio 的 `vcvarsall.bat x64` 并导入其环境。随后 Doctor 会验证 `cl.exe`、`link.exe`、`lib.exe`、`dumpbin.exe`、`msbuild.exe` 和 `nmake.exe`。导入的环境会在 `downloads/.vcenv-cache` 中缓存最多一小时。

MSYS2 根目录默认为 `pkgroot/.../msys2-build-essentials/msys64`。可以通过 `SPC_MSYS2_PATH` 修改；其值必须指向 `msys64` 目录。v3 不再使用 v2 的 PHP SDK 目录和 Visual Studio 版本选项。

### FrankenPHP 所需的 LLVM/Clang

常规 Windows PHP 和依赖库构建使用 MSVC。FrankenPHP 是例外：它的 CGO 链接步骤需要 Clang 和 LLD。

StaticPHP 会优先使用 `CC` 指定的 Clang 绝对路径；否则通过 `vswhere` 查找 Visual Studio LLVM 安装中的 `VC\Tools\Llvm\x64\bin\clang.exe`。构建 FrankenPHP 时，请在 Visual Studio Installer 中安装 **C++ Clang tools for Windows** 组件。这个 Clang 依赖不会取代 PHP 和依赖库构建阶段所需的 MSVC。

## 编译器工具链选择

`GlobalEnvManager` 会先读取 `config/env.ini`（以及可选的 `config/env.custom.ini`），再由 `ToolchainManager` 初始化选定的工具链。常规选择如下：

| 宿主系统 | 默认实现 | 当前存在的其他实现 |
|---|---|---|
| Linux | `ZigToolchain` | `GccNativeToolchain`、`ClangNativeToolchain`、`MuslToolchain` |
| macOS | `ClangNativeToolchain` | `ClangBrewToolchain`、`ClangPortsToolchain` |
| Windows | `MSVCToolchain` | 无 |

在 Unix 上，工具链会提供 `CC`、`CXX`、`AR`、`RANLIB` 和 `LD` 的默认值。调用方已经设置的值优先于 `env.ini` 默认值。`SPC_TARGET` 决定目标操作系统/libc/ABI，`SPC_USE_LLVM` 决定 macOS 的 Clang 分发方式。`SPC_TOOLCHAIN` 可以覆盖实现类，但它目前是维护者级别的内部设置，并非稳定的公开扩展契约。

环境初始化后，工具链会验证其命令以及目标/libc 组合。能够识别编译器时，还会把编译器描述记录到 `PHP_BUILD_COMPILER`。

## 隔离的 pkg-config

Unix 构建会刻意使用由 StaticPHP 管理的 `pkg-config`，而不是碰巧位于系统 `PATH` 首位的可执行文件。`PkgConfigUtil` 按以下顺序搜索：

1. `pkgroot/<platform>/bin/pkg-config`
2. `buildroot/bin/pkg-config`

Doctor 可以通过 `pkg-config` 工具 Package 安装预构建版本。如果需要从源码构建，StaticPHP 会使用其内置 GLib 构建 pkg-config，并禁用系统 include、library、sysroot 和默认 `.pc` 搜索路径。

构建期间，`PKG_CONFIG_PATH` 以 `buildroot/lib/pkgconfig` 开头；StaticPHP 的依赖查询和生成的 CMake toolchain 文件都会请求 `--static` 参数。这样可以避免无关的系统库悄悄满足原本应由 StaticPHP 自行构建的依赖。

## Package 构建 Executor

Package 类使用三种通用 Executor 实现：

| Executor | 平台与构建系统 | 当前行为 |
|---|---|---|
| `UnixAutoconfExecutor` | Linux/macOS，Autoconf | 添加静态/PIC 和 `buildroot` 前缀参数，执行 `configure`、并行 `make`，并可选执行 `make install` |
| `UnixCMakeExecutor` | Linux/macOS，CMake | 生成以 `buildroot` 为根的 toolchain 文件，固定使用 StaticPHP 的编译器和 pkg-config，禁用共享库，然后执行配置、构建和安装 |
| `WindowsCMakeExecutor` | Windows，CMake/MSVC | 使用 x64 Visual Studio generator、静态 MSVC runtime 和 StaticPHP CMake toolchain 文件，并执行 `cmake --build --target install` |

Executor 会初始化 Package 专属的 include、library 和环境路径，并使用配置的构建并发数。Unix Autoconf 失败时会保留 `config.log`，Unix CMake Executor 会把可用的配置/错误/输出日志复制到 `SPC_LOGS_DIR`。Windows CMake Executor 当前依赖通用 shell 和 output 日志，不会另行收集这些 CMake 文件。

维护内置 Package 时，这些类是很有用的实现入口，但其构造函数和链式方法尚未被声明为稳定 PHP API。在公开扩展 API 定义完成前，Package 作者应遵循现有的 `src/Package/Library` 或 `src/Package/Tool` 实现模式。

## 排查工具故障

Doctor 已通过但某个 Package 仍构建失败时：

1. 运行 `spc dev:shell`（或 `bin/spc dev:shell`），检查 `CC`、`CXX`、`AR`、`LD`、`PKG_CONFIG` 和 `PATH`。
2. 确认 `SPC_TARGET` 描述的是预期的 libc 和 ABI。
3. 查看 `log/spc.output.log`、`log/spc.shell.log` 以及 Package 专属的 Autoconf/CMake 日志。
4. 在 Windows 上，如果 Visual Studio、MSYS2 或 LLVM 组件发生变化，请重新运行 Doctor；只有怀疑 Visual Studio 环境缓存过期时，才删除有效期一小时的 `downloads/.vcenv-cache`。

不要通过把二进制复制进 `buildroot/` 或修改生成的构建目录来绕过缺失工具。应根据工具的职责，把它加入宿主机前置依赖、StaticPHP 工具 Package 或 Package 构建依赖。
