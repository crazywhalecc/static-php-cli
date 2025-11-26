# Guide

StaticPHP is a tool used to build statically compiled PHP binaries, 
currently supporting Linux and macOS systems.

In the guide section, you will learn how to use static php cli to build standalone PHP programs.

- [Build (local)](./manual-build)
- [Build (GitHub Actions)](./action-build)
- [Supported Extensions](./extensions)

## Compilation Environment

The following is the architecture support situation, where :gear: represents support for GitHub Action build, 
:computer: represents support for local manual build, and empty represents temporarily not supported.

|         | x86_64                 | aarch64                |
|---------|------------------------|------------------------|
| macOS   | :gear: :computer:      | :gear: :computer:      |
| Linux   | :gear: :computer:      | :gear: :computer:      |
| Windows | :gear: :computer:      |                        |
| FreeBSD | :computer: (:warning:) | :computer: (:warning:) |

> Due to lack of users and testing, FreeBSD is no longer supported in latest StaticPHP project.

Current supported PHP versions for compilation:

> :warning: Partial support, there may be issues with new beta versions and old versions.
>
> :heavy_check_mark: Supported
>
> :x: Not supported

| PHP Version | Status             | Comment                                                                                    |
|-------------|--------------------|--------------------------------------------------------------------------------------------|
| 7.2         | :x:                |                                                                                            |
| 7.3         | :x:                | phpmicro and many extensions do not support 7.3, 7.4 versions                              |
| 7.4         | :x:                | phpmicro and many extensions do not support 7.3, 7.4 versions                              |
| 8.0         | :warning:          | PHP official has stopped maintaining 8.0, we no longer handle 8.0 related backport support |
| 8.1         | :warning:          | PHP official only provides security updates for 8.1                                        |
| 8.2         | :heavy_check_mark: |                                                                                            |
| 8.3         | :heavy_check_mark: |                                                                                            |
| 8.4         | :heavy_check_mark: |                                                                                            |
| 8.5         | :heavy_check_mark: |                                                                                            |

> This table shows the support status of static-php-cli for building corresponding versions, not the PHP official support status for that version.

## PHP Support Versions

Currently, static-php-cli supports PHP versions 8.2 ~ 8.5, and theoretically supports PHP 8.1 and earlier versions, just select the earlier version when downloading.
However, due to some extensions and special components that have stopped supporting earlier versions of PHP, static-php-cli will not explicitly support earlier versions.
We recommend that you compile the latest PHP version possible for a better experience.
