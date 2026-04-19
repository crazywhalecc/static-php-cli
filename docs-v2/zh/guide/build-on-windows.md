# 在 Windows 上构建

因为 Windows 系统是 NT 内核，与类 Unix 的操作系统使用的编译工具及操作系统接口几乎完全不同，所以在 Windows 上的构建流程会与 Unix 系统有些许不同。

## GitHub Actions 构建

现在已支持从 Actions 构建 Windows 版本的 static-php 了。
和 Linux、macOS 一样，你需要先 Fork static-php-cli 仓库到你的 GitHub 账户中，然后你可以进入 [扩展列表](./extensions) 选择要编译的扩展，然后进入自己仓库的 `CI on Windows` 选择 PHP 版本、填入扩展列表（逗号分割），点击 Run 即可。

如果你要在本地开发或构建，请继续向下阅读。

## 环境准备

在 Windows 上构建静态 PHP 所需要的工具与 PHP 官方的 Windows 构建工具是相同的。你可以阅读 [官方文档](https://wiki.php.net/internals/windows/stepbystepbuild_sdk_2)。

总结下来，你需要以下环境及工具：

- Windows 10（需要 build 17063 或以后的更新）
- Visual Studio 2019/2022（推荐 2022）
- Visual Studio 的 C++ 桌面开发
- Git for Windows
- static-php-cli 仓库
- PHP 和 Composer（static-php-cli 需要它们，可使用 `bin/setup-runtime` 自动安装）
- [php-sdk-binary-tools](https://github.com/php/php-sdk-binary-tools)（可使用 doctor 自动安装）
- strawberry-perl（可使用 doctor 自动安装）
- nasm（可使用 doctor 自动安装）

::: tip
static-php-cli 在 Windows 上的构建指的是使用 MSVC 构建 PHP，不基于 MinGW、Cygwin、WSL 等环境。

如果你更倾向使用 WSL，请参考在 Linux 上构建的章节。
:::

在安装 Visual Studio 后，选择 C++ 桌面开发的工作负荷后，可能会下载 8GB 左右的编译工具，下载速度取决于你的网络状况。

### 安装 Git

Git for Windows 可以从 [这里](https://git-scm.com/download/win) 下载并安装 `Standalone Installer 64-bit` 版本，安装在默认位置（`C:\Program Files\Git\`）。
如果不想手动下载和安装，你也可以使用 Visual Studio Installer，在**单个组件**的选择列表中，勾选 Git。

### 准备 static-php-cli

static-php-cli 项目的下载方式很简单，只需要使用 git clone 即可。推荐将项目放在 `C:\spc-build\` 或类似目录，路径最好不要有空格。

```shell
mkdir "C:\spc-build"
cd C:\spc-build
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli
```

static-php-cli 自身需要 PHP 环境，是有点奇怪，但现在可以通过脚本快速安装 PHP 环境。 
一般你的电脑不会安装 Windows 版本的 PHP，所以我们建议你在下载 static-php-cli 后，直接使用 `bin/setup-runtime`，在当前目录安装 PHP 和 Composer。

```shell
# 安装 PHP 和 Composer 到 ./runtime/ 目录
bin/setup-runtime

# 安装后，如需在全局命令中使用 PHP 和 Composer，使用下面的命令将 runtime/ 目录添加到 PATH
bin/setup-runtime -action add-path
# 删除 PATH 中的 runtime/ 目录
bin/setup-runtime -action remove-path
```

在准备好 PHP 和 Composer 环境后，使用 `composer` 安装 static-php-cli 的依赖：

```shell
cd C:\spc-build\static-php-cli
runtime/composer install --no-dev
```

### 自动安装其他依赖

对于 `php-sdk-binary-tools`、`strawberry-perl`、`nasm`，我们更建议你直接使用命令 `bin/spc doctor --auto-fix` 检查并安装。

如果 doctor 成功自动安装，请**跳过**下方手动安装上述工具的步骤。

如果自动安装无法成功的话，再参考下方手动安装的方式。

### 手动安装 php-sdk-binary-tools

```bat
cd C:\spc-build\static-php-cli
git clone https://github.com/php/php-sdk-binary-tools.git
```

> 你也可以在 Windows 设置中设置全局变量 `PHP_SDK_PATH`，并将该项目克隆至变量对应的路径。一般情况下，默认即可。

### 手动安装 strawberry-perl

> 如果你不需要编译 openssl 扩展，可不安装 perl。

1. 从 [GitHub](https://github.com/StrawberryPerl/Perl-Dist-Strawberry/releases/) 下载 strawberry-perl 最新版。
2. 安装到 `C:\spc-build\static-php-cli\pkgroot\perl\` 目录。

> 你可以下载 `-portable` 版本，并直接解压到上述目录。
> 最后的 `perl.exe` 应该位于 `C:\spc-build\static-php-cli\pkgroot\perl\perl\bin\perl.exe`。

### 手动安装 nasm

> 如果你不需要编译 openssl 扩展，可不安装 nasm。

1. 从 [官网](https://www.nasm.us/pub/nasm/releasebuilds/) 下载 nasm 工具（x64）。
2. 将 `nasm.exe`、`ndisasm.exe` 放在 `C:\spc-build\static-php-cli\php-sdk-binary-tools\bin\` 目录。


## 下载源码

见 [本地构建 - download](./manual-build.html#命令-download-下载依赖包)

## 编译 PHP

使用 build 命令可以开始构建静态 php 二进制，在执行 `bin/spc build` 命令前，务必先使用 `download` 命令下载资源，建议使用 `doctor` 检查环境。

### 基本用法

你需要先到 [扩展列表](./extensions) 或 [命令生成器](./cli-generator) 选择你要加入的扩展，然后使用命令 `bin/spc build` 进行编译。你需要指定编译目标，从如下参数中选择：

- `--build-cli`: 构建一个 cli sapi（命令行界面，可在命令行执行 PHP 代码）
- `--build-micro`: 构建一个 micro sapi（用于构建一个包含 PHP 代码的独立可执行二进制）

```shell
# 编译 PHP，附带 bcmath,openssl,zlib 扩展，编译目标为 cli
bin/spc build "bcmath,openssl,zlib" --build-cli

# 编译 PHP，附带 bcmath,openssl,zlib 扩展，编译目标为 micro 和 cli
bin/spc build "bcmath,openssl,zlib" --build-micro --build-cli
```

::: warning
在Windows中，最好使用双引号包裹包含逗号的参数，例如 `"bcmath,openssl,mbstring"`
:::

### 调试

如果你在编译过程中遇到了问题，或者想查看每个执行的 shell 命令，可以使用 `--debug` 开启 debug 模式，查看所有终端日志：

```shell
bin/spc build "openssl" --build-cli --debug
```

### 编译运行选项

在编译过程中，有些特殊情况需要对编译器、编译目录的内容进行干预，可以尝试使用以下命令：

- `--with-clean`: 编译 PHP 前先清理旧的 make 产生的文件
- `--enable-zts`: 让编译的 PHP 为线程安全版本（默认为 NTS 版本）
- `--with-libs=XXX,YYY`: 编译 PHP 前先编译指定的依赖库，激活部分扩展的可选功能
- `--with-config-file-scan-dir=XXX`： 读取 `php.ini` 后扫描 `.ini` 文件的目录（在 [这里](../faq/index.html#php-ini-的路径是什么) 查看默认路径）
- `-I xxx=yyy`: 编译前将 INI 选项硬编译到 PHP 内（支持多个选项，别名是 `--with-hardcoded-ini`）
- `--with-micro-fake-cli`: 在编译 micro 时，让 micro 的 SAPI 伪装为 `cli`（用于兼容一些检查 `PHP_SAPI` 的程序）
- `--disable-opcache-jit`: 禁用 opcache jit（默认启用）
- `--without-micro-ext-test`: 在构建 micro.sfx 后，禁用测试不同扩展在 micro.sfx 的运行结果
- `--with-suggested-exts`: 编译时将 `ext-suggests` 也作为编译依赖加入
- `--with-suggested-libs`: 编译时将 `lib-suggests` 也作为编译依赖加入
- `--with-upx-pack`: 编译后使用 UPX 减小二进制文件体积（需先使用 `bin/spc install-pkg upx` 安装 upx）
- `--with-micro-logo=XXX.ico`: 自定义 micro 构建组合后的 `exe` 可执行文件的图标（格式为 `.ico`）

有关硬编码 INI 选项，下面是一个简单的例子，我们预设一个更大的 `memory_limit`，并且禁用 `system` 函数：

```shell
bin/spc build "bcmath,openssl" --build-cli -I "memory_limit=4G" -I "disable_functions=system"
```

另一个例子：自定义 micro 构建后的 `exe` 程序图标：

```shell
bin/spc build "ffi,bcmath" --build-micro --with-micro-logo=mylogo.ico --debug
bin/spc micro:combine hello.php
# Then we got `my-app.exe` with custom logo!
my-app.exe
```

## 使用 php.exe

php.exe 编译后位于 `buildroot\bin\` 目录，你可以将其拷贝到任意位置使用。

```shell
.\php -v
```

## 使用 micro

> phpmicro 是一个提供自执行二进制 PHP 的项目，本项目依赖 phpmicro 进行编译自执行二进制。详见 [dixyes/phpmicro](https://github.com/dixyes/phpmicro)。

最后编译结果会输出一个 `./micro.sfx` 的文件，此文件需要配合你的 PHP 源码使用。
该文件编译后会存放在 `buildroot/bin/` 目录中。

使用时应准备好你的项目源码文件，可以是单个 PHP 文件，也可以是 Phar 文件。

> 如果要结合 phar 文件，编译时必须包含 phar 扩展！

```shell
# code.php "<?php echo 'Hello world' . PHP_EOL;"
bin/spc micro:combine code.php -O my-app.exe
# Run it!!! Copy it to another computer!!!
./my-app.exe
```

如果打包 PHAR 文件，仅需把 code.php 更换为 phar 文件路径即可。
你可以使用 [box-project/box](https://github.com/box-project/box) 将你的 CLI 项目打包为 Phar，
然后将它与 phpmicro 结合，生成独立可执行的二进制文件。

有关 `micro:combine` 命令的更多细节，请参考 Unix 系统上的 [命令](./manual-build)。
