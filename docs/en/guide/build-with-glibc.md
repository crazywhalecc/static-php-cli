# Build glibc Compatible Linux Binary

## Why Build glibc Compatible Binary

Currently, the binaries built by static-php-cli on Linux by default are based on musl-libc (statically linked). 
musl-libc is a lightweight libc implementation 
that aims to be compatible with glibc and provides good support for pure static linking. 
This means that the compiled static PHP executable can be used on almost any Linux distribution without worrying about the versions of libc, libstdc++, etc.

However, there are some issues with pure static linking of musl-libc binaries on Linux:

- The `dl()` function in PHP cannot be used to load dynamic libraries and external PHP extensions.
- The FFI extension in PHP cannot be used.
- In some extreme cases, performance issues may occur. See [musl-libc performance issues](https://github.com/php/php-src/issues/13648).

Different Linux distributions use different default libc. 
For example, Alpine Linux uses musl libc, while most Linux distributions use glibc. 
However, even so, we cannot directly use any distribution using glibc to build portable static binaries because glibc has some issues:

- Binaries built with gcc and other tools on newer versions of distributions cannot run on older versions of distributions.
- glibc is not recommended to be statically linked because some of its features require the support of dynamic libraries.

However, we can use Docker to solve this problem. 
The final output is a binary **dynamically linked with glibc** and some necessary libraries, 
but **statically linked with all other dependencies**.

1. Use an older version of a Linux distribution (such as CentOS 7.x), which has an older version of glibc but can run on most modern Linux distributions.
2. Build the static binary of PHP in this container so that it can run on most modern Linux distributions.

> Using glibc static binaries can run on most modern Linux distributions but cannot run on musl libc distributions, such as CentOS 6, Alpine Linux, etc.

## Build glibc Compatible Linux Binary

The latest version of static-php-cli includes the `bin/spc-gnu-docker` script, 
which can create a CentOS 7.x (glibc-2.17) Docker container with one click and build a glibc compatible PHP static binary in the container.

First, clone the repository of this project and add the following content to the `config/env.custom.ini` file:

```ini
; Modify this file name to `env.custom.ini`, and run `bin/spc-gnu-docker`,
; you can compile a GNU libc based static binary !
[global]
SPC_SKIP_DOCTOR_CHECK_ITEMS="if musl-wrapper is installed,if musl-cross-make is installed"

[linux]
CC=gcc
CXX=g++
AR=ar
LD=ld
SPC_DEFAULT_C_FLAGS=-fPIC
SPC_NO_MUSL_PATH=yes
SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM="-Wl,-O1 -pie"
SPC_CMD_VAR_PHP_MAKE_EXTRA_LIBS="-ldl -lpthread -lm -lresolv -lutil"
```

Then, run the following command once. 
The first run will take a long time because it needs to download the CentOS 7.x image and some build tools.

```bash
bin/spc-gnu-docker
```

After the image is built, you will see the same command help menu as `bin/spc`, which means the container is ready.

After the container is ready, you can refer to the [local build](./manual-build) section to build your PHP static binary. 
Just replace `bin/spc` or `./spc` with `bin/spc-gnu-docker`.

Unlike the default build, when building in the glibc environment, you **must** add the parameter `--libc=glibc`, such as:

```bash
bin/spc-gnu-docker --libc=glibc build bcmath,ctype,openssl,pdo,phar,posix,session,tokenizer,xml,zip --build-cli --debug
```

## Notes

In rare cases, glibc-based static PHP may encounter segment faults and other errors, but there are currently few examples. 
If you encounter any issues, please submit an issue.

glibc build is an extended feature and is not part of the default static-php support. 
If you have related issues or requirements, please indicate that you are building based on glibc when submitting an issue.

If you need to build glibc-based binaries without using Docker, 
please refer to the `bin/spc-gnu-docker` script to manually create a similar environment.

Since glibc binaries are not the main goal of the project, 
we generally do not test the compatibility of various libraries and extensions under glibc.
If any specific library builds successfully on musl-libc but fails on glibc, please submit an issue.
