# 构建 glibc 兼容的 Linux 二进制

## 为什么要构建 glibc 兼容的二进制

目前，static-php-cli 在默认条件下在 Linux 系统构建的二进制都是基于 musl-libc（静态链接）的。
musl-libc 是一个轻量级的 libc 实现，它的目标是与 glibc 兼容，并且提供良好的纯静态链接支持。
这意味着，编译出来的静态 PHP 可执行文件在几乎任何 Linux 发行版都可以使用，而不需要考虑 libc、libstdc++ 等库的版本问题。

但是，Linux 系统的纯静态链接 musl-libc 二进制文件存在以下问题：

- 无法使用 PHP 的 `dl()` 函数加载动态链接库和外部 PHP 扩展。
- 无法使用 PHP 的 FFI 扩展。
- 部分极端情况下，可能会出现性能问题，参见 [musl-libc 的性能问题](https://github.com/php/php-src/issues/13648)。

对于不同的 Linux 发行版，它们使用的默认 libc 可能不同，比如 Alpine Linux 使用 musl libc，而大多数 Linux 发行版使用 glibc。
但即便如此，我们也不能直接使用任意的发行版和 glibc 构建便携的静态二进制文件，因为 glibc 有一些问题：

- 基于新版本的发行版在使用 gcc 等工具构建的二进制，无法在旧版本的发行版上运行。
- glibc 不推荐被静态链接，因为它的一些特性需要动态链接库的支持。

但是，我们可以使用 Docker 容器来解决这个问题，最终输出的结果是一个动态链接 glibc 和一些必要库的二进制，但它静态链接所有其他依赖。

1. 使用一个旧版本的 Linux 发行版（如 CentOS 7.x），它的 glibc 版本比较旧，但是可以在大多数现代 Linux 发行版上运行。
2. 在这个容器中构建 PHP 的静态二进制文件，这样就可以在大多数现代 Linux 发行版上运行了。

> 使用 glibc 的静态二进制文件，可以在大多数现代 Linux 发行版上运行，但是不能在 musl libc 的发行版上运行，如 CentOS 6、Alpine Linux 等。

## 构建 glibc 兼容的 Linux 二进制

最新版的 static-php-cli 内置了 `bin/spc-gnu-docker` 脚本，可以一键创建一个 CentOS 7.x (glibc-2.17) 的 Docker 容器，并在容器中构建 glibc 兼容的 PHP 静态二进制文件。

请先克隆本项目的仓库，并将下面的内容添加到 `config/env.custom.ini` 文件中：

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

然后，先运行一次以下命令。首次运行时时间较长，因为需要下载 CentOS 7.x 的镜像和一些编译工具。

```bash
bin/spc-gnu-docker
```

构建镜像完成后，你将看到和 `bin/spc` 一样的命令帮助菜单，这时说明容器已经准备好了。

在容器准备好后，你可以参考 [本地构建](./manual-build) 章节的内容，构建你的 PHP 静态二进制文件。仅需要把 `bin/spc` 或 `./spc` 替换为 `bin/spc-gnu-docker` 即可。

与默认构建不同的是，在 glibc 环境构建时**必须添加**参数 `--libc=glibc`，如：

```bash
bin/spc-gnu-docker --libc=glibc build bcmath,ctype,openssl,pdo,phar,posix,session,tokenizer,xml,zip --build-cli --debug
```

## 注意事项

极少数情况下，基于 glibc 的静态 PHP 可能会出现 segment fault 等错误，但目前例子较少，如果遇到问题请提交 issue。

glibc 构建为扩展的特性，不属于默认 static-php 的支持范围。如果有相关问题或需求，请在提交 Issue 时注明你是基于 glibc 构建的。

如果你需要不使用 Docker 构建基于 glibc 的二进制，请参考 `bin/spc-gnu-docker` 脚本，手动构建一个类似的环境。

由于 glibc 二进制不是项目的主要目标，一般情况下我们不会额外测试 glibc 下的各个库和扩展的兼容性。
任何特定库如果在 musl-libc 上构建成功，但在 glibc 上构建失败，请提交 issue，我们将会单独解决。