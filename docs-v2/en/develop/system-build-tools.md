# Compilation Tools

static-php-cli uses many system compilation tools when building static PHP. These tools mainly include:

- `autoconf`: used to generate `configure` scripts.
- `make`: used to execute `Makefile`.
- `cmake`: used to execute `CMakeLists.txt`.
- `pkg-config`: Used to find the installation path of dependent libraries.
- `gcc`: used to compile C/C++ projects under Linux.
- `clang`: used to compile C/C++ projects under macOS.

For Linux and macOS operating systems, 
these tools can usually be installed through the package manager, which is written in the doctor module.
Theoretically we can also compile and download these tools manually, 
but this will increase the complexity of compilation, so we do not recommend this.

## Linux Compilation Tools

For Linux systems, different distributions have different installation methods for compilation tools. 
And for static compilation, the package management of some distributions cannot install libraries and tools for pure static compilation.
Therefore, for the Linux platform and its different distributions, 
we currently provide a variety of compilation environment preparations.

### Glibc Environment

The glibc environment refers to the underlying `libc` library of the system 
(that is, the C standard library that all programs written in C language are dynamically linked to) uses `glibc`, 
which is the default environment for most distributions.
For example: Ubuntu, Debian, CentOS, RHEL, openSUSE, Arch Linux, etc.

In the glibc environment, the package management and compiler we use point to glibc by default, 
and glibc cannot be statically linked well. 
One of the reasons it cannot be statically linked is that its network library `nss` cannot be compiled statically.

For the glibc environment, in static-php-cli and spc in 2.0-RC8 and later, you can choose two ways to build static PHP:

1. Use Docker to build, you can use `bin/spc-alpine-docker` to build, it will build an Alpine Linux docker image.
2. Use `bin/spc doctor --auto-fix` to install the `musl-wrapper` and `musl-cross-make` packages, and then build directly. 
([Related source code](https://github.com/crazywhalecc/static-php-cli/blob/main/src/SPC/doctor/item/LinuxMuslCheck.php))

Generally speaking, the build results in these two environments are consistent, and you can choose according to actual needs.

In the doctor module, static-php-cli will first detect the current Linux distribution. 
If the current distribution is a glibc environment, you will be prompted to install the musl-wrapper and musl-cross-make packages.

The process of installing `musl-wrapper` in the glibc environment is as follows:

1. Download the specific version of [musl-wrapper source code](https://musl.libc.org/releases/) from the musl official website.
2. Use `gcc` installed from the package management to compile the musl-wrapper source code and generate `musl-libc` and other libraries: `./configure --disable-gcc-wrapper && make -j && sudo make install`.
3. The musl-wrapper related libraries will be installed in the `/usr/local/musl` directory.

The process of installing `musl-cross-make` in the glibc environment is as follows:

1. Download the precompiled [musl-cross-make](https://dl.static-php.dev/static-php-cli/deps/musl-toolchain/) compressed package from dl.static-php.dev .
2. Unzip to the `/usr/local/musl` directory.

::: tip
In the glibc environment, static compilation can be achieved by directly installing musl-wrapper, 
but musl-wrapper only contains `musl-gcc` and not `musl-g++`, which means that C++ code cannot be compiled.
So we need musl-cross-make to provide `musl-g++`.

The reason why the musl-cross-make package cannot be compiled directly locally is that 
its compilation environment requirements are relatively high (requires more than 36GB of memory, compiled under Alpine Linux), 
so we provide precompiled binary packages that can be used for all Linux distributions.

At the same time, the package management of some distributions provides musl-wrapper, 
but musl-cross-make needs to match the corresponding musl-wrapper version, 
so we do not use package management to install musl-wrapper.

Compiling musl-cross-make will be introduced in the **musl-cross-make Toolchain Compilation** section of this chapter.
:::

### Musl Environment

The musl environment refers to the system's underlying `libc` library that uses `musl`, 
which is a lightweight C standard library that can be well statically linked.

For the currently popular Linux distributions, Alpine Linux uses the musl environment, 
so static-php-cli can directly build static PHP under Alpine Linux. 
You only need to install basic compilation tools (such as `gcc`, `cmake`, etc.) directly from the package management.

For other distributions, if your distribution uses the musl environment, 
you can also use static-php-cli to build static PHP directly after installing the necessary compilation tools.

::: tip
In the musl environment, static-php-cli will automatically skip the installation of musl-wrapper and musl-cross-make.
:::

### Docker Environment

The Docker environment refers to using Docker containers to build static PHP. You can use `bin/spc-alpine-docker` to build.
Before executing this command, you need to install Docker first, and then execute `bin/spc-alpine-docker` in the project root directory.

After executing `bin/spc-alpine-docker`, static-php-cli will automatically download the Alpine Linux image and then build a `cwcc-spc-x86_64` or `cwcc-spc-aarch64` image.
Then all build process is performed within this image, which is equivalent to compiling in Alpine Linux.

## musl-cross-make Toolchain Compilation

In Linux, although you do not need to manually compile the musl-cross-make tool, 
if you want to understand its compilation process, you can refer here.
Another important reason is that this may not be compiled using automated tools such as CI and Actions, 
because the existing CI service compilation environment does not meet the compilation requirements of musl-cross-make, 
and the configuration that meets the requirements is too expensive.

The compilation process of musl-cross-make is as follows:

Prepare an Alpine Linux environment (either directly installed or using Docker). 
The compilation process requires more than **36GB** of memory, 
so you need to compile on a machine with larger memory. 
Without this much memory, compilation may fail.

Then write the following content into the `config.mak` file:

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

And also you need to add `gcc-13.2.0.tar.xz.sha1` file, contents here:

```
5f95b6d042fb37d45c6cbebfc91decfbc4fb493c  gcc-13.2.0.tar.xz
```

If you are using Docker to build, create a new `Dockerfile` file and write the following content:

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

If you are using Alpine Linux in a non-Docker environment, you can directly execute the commands in the Dockerfile, for example:

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
# Copy config.mak to the working directory of musl-cross-make.
# You need to replace /path/to/config.mak with your config.mak file path.
cp /path/to/config.mak musl-cross-make/
cp /path/to/gcc-13.2.0.tar.xz.sha1 musl-cross-make/hashes

make TARGET=x86_64-linux-musl -j || :
sed -i 's/poison calloc/poison/g' ./gcc-13.2.0/gcc/system.h
make TARGET=x86_64-linux-musl -j
make TARGET=x86_64-linux-musl install -j
tar cvzf x86_64-musl-toolchain.tgz output/*
```

::: tip
All the above scripts are suitable for x86_64 architecture Linux. 
If you need to build musl-cross-make for the ARM environment, just replace all `x86_64` above with `aarch64`.
:::

This compilation process may fail due to insufficient memory, network problems, etc. 
You can try a few more times, or use a machine with larger memory to compile.
If you encounter problems or you have better improvement solutions, go to [Discussion](https://github.com/crazywhalecc/static-php-cli-hosted/issues/1).

## macOS Environment

For macOS systems, the main compilation tool we use is `clang`, 
which is the default compiler for macOS systems and is also the compiler of Xcode.

Compiling under macOS mainly relies on Xcode or Xcode Command Line Tools. 
You can download Xcode from the App Store, 
or execute `xcode-select --install` in the terminal to install Xcode Command Line Tools.

In addition, in the `doctor` environment check module, static-php-cli will check whether Homebrew, 
compilation tools, etc. are installed on the macOS system. 
If not, you will be prompted to install them. I will not go into details here.

## FreeBSD Environment

FreeBSD is also a Unix system, and its compilation tools are similar to macOS. 
You can directly use the package management `pkg` to install `clang` and other compilation tools through the `doctor` command.

## pkg-config Compilation (*nix only)

If you observe the compilation log when using static-php-cli to build static PHP, you will find that no matter what is compiled, 
`pkg-config` will be compiled first. This is because `pkg-config` is a library used to find dependencies.
In earlier versions of static-php-cli, we directly used the `pkg-config` tool installed by package management, 
but this would cause some problems, such as:

- Even if `PKG_CONFIG_PATH` is specified, `pkg-config` will try to find dependent packages from the system path.
- Since `pkg-config` will look for dependent packages from the system path, 
  if a dependent package with the same name exists in the system, compilation may fail.

In order to avoid the above problems, we compile `pkg-config` into `buildroot/bin` in user mode and use it. 
We use parameters such as `--without-sysroot` to avoid looking for dependent packages from the system path.
