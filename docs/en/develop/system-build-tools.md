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
2. Use `bin/spc doctor --auto-fix` and then build for glibc directly. 
([Related source code](https://github.com/crazywhalecc/static-php-cli/blob/main/src/SPC/doctor/item/LinuxMuslCheck.php))


### Musl Environment

The musl environment refers to the system's underlying `libc` library that uses `musl`, 
which is a lightweight C standard library that can be well statically linked.

For the currently popular Linux distributions, Alpine Linux uses the musl environment, 
so static-php-cli can directly build static PHP under Alpine Linux. 
You only need to install basic compilation tools (such as `gcc`, `cmake`, etc.) directly from the package management.

For other distributions, if your distribution uses the musl environment, 
you can also use static-php-cli to build static PHP directly after installing the necessary compilation tools.

### Docker Environment

The Docker environment refers to using Docker containers to build static PHP. You can use `bin/spc-alpine-docker` or `bin/spc-gnu-docker` to build.
Before executing this command, you need to install Docker first.

After executing `bin/spc-alpine-docker`, static-php-cli will automatically download the Alpine Linux image and then build a `cwcc-spc-x86_64` or `cwcc-spc-aarch64` image.
Then all build process is performed within this image, which is equivalent to compiling in Alpine Linux.

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
