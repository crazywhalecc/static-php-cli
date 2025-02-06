# Guide

Static php cli is a tool used to build statically compiled PHP binaries, 
currently supporting Linux and macOS systems.

In the guide section, you will learn how to use static php cli to build standalone PHP programs.

- [Build (local)](./manual-build)
- [Build (GitHub Actions)](./action-build)
- [Supported Extensions](./extensions)

## Compilation Environment

The following is the architecture support situation, where :gear: represents support for GitHub Action build, 
:computer: represents support for local manual build, and empty represents temporarily not supported.

|         | x86_64            | aarch64           |
|---------|-------------------|-------------------|
| macOS   | :gear: :computer: | :gear: :computer: |
| Linux   | :gear: :computer: | :gear: :computer: |
| Windows | :gear: :computer: |                   |
| FreeBSD | :computer:        | :computer:        |

Among them, Linux is currently only tested on Ubuntu, Debian, and Alpine distributions, 
and other distributions have not been tested, which cannot guarantee successful compilation.
For untested distributions, local compilation can be done using methods such as Docker to avoid environmental issues.

There are two architectures for macOS: `x86_64` and `Arm`, but binaries compiled on one architecture cannot be directly used on the other architecture.
Rosetta 2 cannot guarantee that programs compiled with `Arm` architecture can fully run on `x86_64` environment.

Windows currently only supports the x86_64 architecture, and does not support 32-bit x86 or arm64 architecture.

## Supported PHP Version

Currently, static php cli supports PHP versions 8.1 to 8.4, and theoretically supports PHP 8.0 and earlier versions. 
Simply select the earlier version when downloading.
However, due to some extensions and special components that have stopped supporting earlier versions of PHP, 
static-php-cli will not explicitly support earlier versions.
We recommend that you compile the latest PHP version possible for a better experience.
