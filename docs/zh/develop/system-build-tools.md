# 系统编译工具

static-php-cli 在构建静态 PHP 时使用了许多系统编译工具，这些工具主要包括：

- `autoconf`: 用于生成 `configure` 脚本。
- `make`: 用于执行 `Makefile`。
- `cmake`: 用于执行 `CMakeLists.txt`。
- `pkg-config`: 用于查找依赖库的安装路径。
- `gcc`: 用于在 Linux 下编译 C/C++ 语言代码。
- `clang`: 用于在 macOS 下编译 C/C++ 语言代码。

对于 Linux 和 macOS 操作系统，这些工具通常可以通过包管理安装，这部分在 doctor 模块中编写了。
理论上我们也可以通过编译和手动下载这些工具，但这样会增加编译的复杂度，所以我们不推荐这样做。

## Linux 环境编译工具

对于 Linux 系统来说，不同发行版的编译工具安装方式不同。而且对于静态编译来说，某些发行版的包管理无法安装用于纯静态编译的库和工具，
所以对于 Linux 平台及其不同发行版，我们目前提供了多种编译环境的部署措施。

### glibc 环境

glibc 环境指的是系统底层的 `libc` 库（即所有 C 语言编写的程序动态链接的 C 标准库）使用的是 `glibc`，这是大多数发行版的默认环境。
例如：Ubuntu、Debian、CentOS、RHEL、openSUSE、Arch Linux 等。

而 glibc 环境下，我们使用的包管理、编译器都是默认指向 glibc 的，glibc 不能被良好地静态链接。它不能被静态链接的原因之一是它的网络库 `nss` 无法静态编译。

对于 glibc 环境，在 static-php-cli 及 spc 中，你可以选择两种方式来构建静态 PHP：

1. 使用 Docker 构建，你可以使用 `bin/spc-alpine-docker` 或 `bin/spc-gnu-docker` 来构建。
2. 使用 `bin/spc doctor --auto-fix` 然后直接构建 glibc。）

### musl 环境

musl 环境指的是系统底层的 `libc` 库使用的是 `musl`，这是一种轻量级的 C 标准库，它的特点是可以被良好地静态链接。

对于目前流行的 Linux 发行版，Alpine Linux 使用的就是 musl 环境，所以 static-php-cli 在 Alpine Linux 下可以直接构建静态 PHP，仅需直接从包管理安装基础编译工具（如 gcc、cmake 等）即可。

对于其他发行版，如果你的发行版使用的是 musl 环境，那么你也可以在安装必要的编译工具后直接使用 static-php-cli 构建静态 PHP。

### Docker 环境

Docker 环境指的是使用 Docker 容器来构建静态 PHP，你可以使用 `bin/spc-alpine-docker` 或 `bin/spc-gnu-docker` 来构建。
执行这个命令前需要先安装 Docker，然后在项目根目录执行 `bin/spc-alpine-docker` 即可。

在执行 `bin/spc-alpine-docker` 后，static-php-cli 会自动下载 Alpine Linux 镜像，然后构建一个 `cwcc-spc-x86_64` 或 `cwcc-spc-aarch64` 的镜像。
然后一切的构建都在这个镜像内进行，相当于在 Alpine Linux 内编译。总的来说，Docker 环境就是 musl 环境。

## macOS 环境编译工具

对于 macOS 系统来说，我们使用的编译工具主要是 `clang`，它是 macOS 系统默认的编译器，同时也是 Xcode 的编译器。

在 macOS 下编译，主要依赖于 Xcode 或 Xcode Command Line Tools，你可以在 App Store 下载 Xcode，或者在终端执行 `xcode-select --install` 来安装 Xcode Command Line Tools。

此外，在 `doctor` 环境检查模块中，static-php-cli 会检查 macOS 系统是否安装了 Homebrew、编译工具等，如果没有，会提示你安装，这里不再赘述。

## FreeBSD 环境编译工具

FreeBSD 也是 Unix 系统，它的编译工具和 macOS 类似，你可以直接使用包管理 `pkg` 安装 `clang` 等编译工具，通过 `doctor` 命令。

## pkg-config 编译

如果你在使用 static-php-cli 构建静态 PHP 时仔细观察编译的日志，你会发现无论编译什么，都会先编译 `pkg-config`，这是因为 `pkg-config` 是一个用于查找依赖库的工具。
在早期的 static-php-cli 版本中，我们直接使用了包管理安装的 `pkg-config` 工具，但是这样会导致一些问题，例如：

- 即使指定了 `PKG_CONFIG_PATH`，`pkg-config` 也会尝试从系统路径中查找依赖包。
- 由于 `pkg-config` 会从系统路径中查找依赖包，所以如果系统中存在同名的依赖包，可能会导致编译失败。

为了避免以上问题，我们将 `pkg-config` 编译到用户态的 `buildroot/bin` 内并使用，使用了 `--without-sysroot` 等参数来避免从系统路径中查找依赖包。
