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

对于 glibc 环境，在 2.0 RC8 及以后的 static-php-cli 及 spc 中，你可以选择两种方式来构建静态 PHP：

1. 使用 Docker 构建，这是最简单的方式，你可以使用 `bin/spc-alpine-docker` 来构建，它会在 Alpine Linux 环境下构建。
2. 使用 `bin/spc doctor` 安装 musl-wrapper 和 musl-cross-make 套件，然后直接正常构建。（[相关源码](https://github.com/crazywhalecc/static-php-cli/blob/main/src/SPC/doctor/item/LinuxMuslCheck.php)）

一般来说，这两种构建方式的构建结果是一致的，你可以根据实际需求选择。

在 doctor 模块中，static-php-cli 会先检测当前的 Linux 发行版。如果当前发行版是 glibc 环境，会提示需要安装 musl-wrapper 和 musl-cross-make 套件。

在 glibc 环境下安装 musl-wrapper 的过程如下：

1. 从 musl 官网下载特定版本的 [musl-wrapper 源码](https://musl.libc.org/releases/)。
2. 使用从包管理安装的 `gcc` 编译 musl-wrapper 源码，生成 `musl-libc` 等库：`./configure --disable-gcc-wrapper && make -j && sudo make install`。
3. musl-wrapper 相关库将被安装在 `/usr/local/musl` 目录。

在 glibc 环境下安装 musl-cross-make 的过程如下：

1. 从 dl.static-php.dev 下载预编译好的 [musl-cross-make](https://dl.static-php.dev/static-php-cli/deps/musl-toolchain/) 压缩包。
2. 解压到 `/usr/local/musl` 目录。

::: tip
在 glibc 环境下，静态编译可以通过直接安装 musl-wrapper 来实现，但是 musl-wrapper 仅包含了 `musl-gcc`，而没有 `musl-g++`，这也就意味着无法编译 C++ 代码。
所以我们需要 musl-cross-make 来提供 `musl-g++`。

而 musl-cross-make 套件无法在本地直接编译的原因是它的编译环境要求比较高（需要 36GB 以上内存，Alpine Linux 下编译），所以我们提供了预编译好的二进制包，可用于所有 Linux 发行版。

同时，部分发行版的包管理提供了 musl-wrapper，但 musl-cross-make 需要匹配对应的 musl-wrapper 版本，所以我们不使用包管理安装 musl-wrapper。

对于如何编译 musl-cross-make，将在本章节内的 **编译 musl-cross-make** 小节中介绍。
:::

### musl 环境

musl 环境指的是系统底层的 `libc` 库使用的是 `musl`，这是一种轻量级的 C 标准库，它的特点是可以被良好地静态链接。

对于目前流行的 Linux 发行版，Alpine Linux 使用的就是 musl 环境，所以 static-php-cli 在 Alpine Linux 下可以直接构建静态 PHP，仅需直接从包管理安装基础编译工具（如 gcc、cmake 等）即可。

对于其他发行版，如果你的发行版使用的是 musl 环境，那么你也可以在安装必要的编译工具后直接使用 static-php-cli 构建静态 PHP。

::: tip
在 musl 环境下，static-php-cli 会自动跳过 musl-wrapper 和 musl-cross-make 的安装。
:::

### Docker 环境

Docker 环境指的是使用 Docker 容器来构建静态 PHP，你可以使用 `bin/spc-alpine-docker` 来构建。
执行这个命令前需要先安装 Docker，然后在项目根目录执行 `bin/spc-alpine-docker` 即可。

在执行 `bin/spc-alpine-docker` 后，static-php-cli 会自动下载 Alpine Linux 镜像，然后构建一个 `cwcc-spc-x86_64` 或 `cwcc-spc-aarch64` 的镜像。
然后一切的构建都在这个镜像内进行，相当于在 Alpine Linux 内编译。总的来说，Docker 环境就是 musl 环境。

## musl-cross-make 工具链编译

在 Linux 中，尽管你不需要手动编译 musl-cross-make 工具，但是如果你想了解它的编译过程，可以参考这里。
还有一个重要的原因就是，这个可能无法使用 CI、Actions 等自动化工具编译，因为现有的 CI 服务编译环境不满足 musl-cross-make 的编译要求，满足要求的配置价格太高。

musl-cross-make 的编译过程如下：

准备一个 Alpine Linux 环境（直接安装或使用 Docker 均可），编译的过程需要 36GB 以上内存，所以你需要在内存较大的机器上编译。如果没有这么多内存，可能会导致编译失败。

然后将以下内容写入 `config.mak` 文件内：

```makefile
STAT = -static --static
FLAG = -g0 -Os -Wno-error

ifneq ($(NATIVE),)
COMMON_CONFIG += CC="$(HOST)-gcc ${STAT}" CXX="$(HOST)-g++ ${STAT}"
else
COMMON_CONFIG += CC="gcc ${STAT}" CXX="g++ ${STAT}"
endif

COMMON_CONFIG += CFLAGS="${FLAG}" CXXFLAGS="${FLAG}" LDFLAGS="${STAT}"

BINUTILS_CONFIG += --enable-gold=yes --enable-gprofng=no
GCC_CONFIG += --enable-static-pie --disable-cet --enable-default-pie  
#--enable-default-pie

CONFIG_SUB_REV = 888c8e3d5f7b
GCC_VER = 13.2.0
BINUTILS_VER = 2.40
MUSL_VER = 1.2.4
GMP_VER = 6.2.1
MPC_VER = 1.2.1
MPFR_VER = 4.2.0
LINUX_VER = 6.1.36
```

同时，你需要新建一个 `gcc-13.2.0.tar.xz.sha1` 文件，文件内容如下：

```
5f95b6d042fb37d45c6cbebfc91decfbc4fb493c  gcc-13.2.0.tar.xz
```

如果你使用的是 Docker 构建，新建一个 `Dockerfile` 文件，写入以下内容：

```dockerfile
FROM alpine:edge

RUN apk add --no-cache \
gcc g++ git make curl perl \
rsync patch wget libtool \
texinfo autoconf automake \
bison tar xz bzip2 zlib \
file binutils flex \
linux-headers libintl \
gettext gettext-dev icu-libs pkgconf \
pkgconfig icu-dev bash \
ccache libarchive-tools zip

WORKDIR /opt

RUN git clone https://git.zv.io/toolchains/musl-cross-make.git
WORKDIR /opt/musl-cross-make
COPY config.mak /opt/musl-cross-make
COPY gcc-13.2.0.tar.xz.sha1 /opt/musl-cross-make/hashes

RUN make TARGET=x86_64-linux-musl -j || :
RUN sed -i 's/poison calloc/poison/g' ./gcc-13.2.0/gcc/system.h
RUN make TARGET=x86_64-linux-musl -j
RUN make TARGET=x86_64-linux-musl install -j
RUN tar cvzf x86_64-musl-toolchain.tgz output/*
```

如果你使用的是非 Docker 环境的 Alpine Linux，可以直接执行 Dockerfile 中的命令，例如：

```bash
apk add --no-cache \
gcc g++ git make curl perl \
rsync patch wget libtool \
texinfo autoconf automake \
bison tar xz bzip2 zlib \
file binutils flex \
linux-headers libintl \
gettext gettext-dev icu-libs pkgconf \
pkgconfig icu-dev bash \
ccache libarchive-tools zip

git clone https://git.zv.io/toolchains/musl-cross-make.git
# 将 config.mak 拷贝到 musl-cross-make 的工作目录内，你需要将 /path/to/config.mak 替换为你的 config.mak 文件路径
cp /path/to/config.mak musl-cross-make/
cp /path/to/gcc-13.2.0.tar.xz.sha1 musl-cross-make/hashes

make TARGET=x86_64-linux-musl -j || :
sed -i 's/poison calloc/poison/g' ./gcc-13.2.0/gcc/system.h
make TARGET=x86_64-linux-musl -j
make TARGET=x86_64-linux-musl install -j
tar cvzf x86_64-musl-toolchain.tgz output/*
```

::: tip
以上所有脚本都适用于 x86_64 架构的 Linux。如果你需要构建 ARM 环境的 musl-cross-make，只需要将上方所有 `x86_64` 替换为 `aarch64` 即可。
:::

这个编译过程可能会因为内存不足、网络问题等原因导致编译失败，你可以多尝试几次，或者使用更大内存的机器来编译。
如果遇到了问题，或者你有更好的改进方案，可以在 [讨论](https://github.com/crazywhalecc/static-php-cli-hosted/issues/1) 中提出。

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
