# 项目结构简介

static-php-cli 主要包含三种逻辑组件：资源、依赖库、扩展。这三种组件四个配置文件：`source.json`、`lib.json`、`ext.json`、`pkg.json`。

一个完整的构建静态 PHP 流程是：

1. 使用资源下载模块 `Downloader` 下载指定或所有资源，这些资源包含 PHP 源码、依赖库源码、扩展源码。
2. 使用资源解压模块 `SourceExtractor` 解压下载的资源到编译目录。
3. 使用依赖工具计算出当前加入的扩展的依赖扩展、依赖库，然后对每个需要编译的依赖库进行编译，按照依赖顺序。
4. 使用对应操作系统下的 `Builder` 构建每个依赖库后，将其安装到 `buildroot` 目录。
5. 如果包含外部扩展（源码没有包含在 PHP 内的扩展），将外部扩展拷贝到 `source/php-src/ext/` 目录。
6. 使用 `Builder` 构建 PHP 源码，将其安装到 `buildroot` 目录。

项目主要分为几个文件夹：

- `bin/`: 用于存放程序入口文件，包含 `bin/spc`、`bin/spc-alpine-docker`、`bin/setup-runtime`。
- `config/`: 包含了所有项目支持的扩展、依赖库以及这些资源下载的地址、下载方式等，：`lib.json`、`ext.json`、`source.json`、`pkg.json`、`pre-built.json`。
- `src/SPC/`: 项目的核心代码，包含了整个框架以及编译各种扩展和库的命令。
- `src/globals/`: 项目的全局方法和常量、运行时需要的测试文件（例如：扩展的可用性检查代码）。
- `vendor/`: Composer 依赖的目录，你无需对它做出任何修改。

其中运行原理就是启动一个 `symfony/console` 的 `ConsoleApplication`，然后解析用户在终端输入的命令。

## 基本命令行结构

`bin/spc` 是一个 PHP 代码入口文件，包含了 Unix 通用的 `#!/usr/bin/env php` 用来让系统自动以系统安装好的 PHP 解释器执行。
在项目执行了 `new ConsoleApplication()` 后，框架会自动使用反射的方式，解析 `src/SPC/command` 目录下的所有类，并将其注册成为命令。

项目并没有直接使用 Symfony 推荐的 Command 注册方式和命令执行方式，这里做出了一点小变动：

1. 每个命令都使用 `#[AsCommand()]` Attribute 来注册名称和简介。
2. 将 `execute()` 抽象化，让所有命令基于 `BaseCommand`（它基于 `Symfony\Component\Console\Command\Command`），每个命令本身的执行代码写到了 `handle()` 方法中。
3. `BaseCommand` 添加了变量 `$no_motd`，用于是否在该命令执行时显示 Figlet 欢迎词。
4. `BaseCommand` 将 `InputInterface` 和 `OutputInterface` 保存为成员变量，你可以在命令的类内使用 `$this->input` 和 `$this->output`。

## 基本源码结构

项目的源码位于 `src/SPC` 目录，支持 PSR-4 标准的自动加载，包含以下子目录和类：

- `src/SPC/builder/`: 用于不同操作系统下构建依赖库、PHP 及相关扩展的核心编译命令代码，还包含了一些编译的系统工具方法。
- `src/SPC/command/`: 项目的所有命令都在这里。
- `src/SPC/doctor/`: Doctor 模块，它是一个较为独立的用于检查系统环境的模块，可使用命令 `bin/spc doctor` 进入。
- `src/SPC/exception/`: 异常类。
- `src/SPC/store/`: 有关存储、文件和资源的类都在这里。
- `src/SPC/util/`: 一些可以复用的工具方法都在这里。
- `src/SPC/ConsoleApplication.php`: 命令行程序入口文件。

如果你阅读过源码，你可能会发现还有一个 `src/globals/` 目录，它是用于存放一些全局变量、全局方法、构建过程中依赖的非 PSR-4 标准的 PHP 源码，例如测试扩展代码等。

## Phar 应用目录问题

和其他 php-cli 项目一样，spc 自身对路径有额外的考虑。
因为 spc 可以在 `php-cli directly`、`micro SAPI`、`php-cli with Phar`、`vendor with Phar` 等多种模式下运行，各类根目录存在歧义。这里会进行一个完整的说明。
此问题一般常见于 PHP 项目中存取文件的基类路径选择问题，尤其是在配合 `micro.sfx` 使用时容易出现路径问题。

注意，此处仅对你在开发 Phar 项目或 PHP 框架时可能有用。

> 接下来我们都将 `static-php-cli`（也就是 spc）当作一个普通的 `php` 命令行程序来看，你可以将 spc 理解为你自己的任何 php-cli 应用以参考。

下面主要有三个基本的常量理论值，我们建议你在编写 php 项目时引入这三种常量：

- `WORKING_DIR`：执行 PHP 脚本时的工作目录
- `SOURCE_ROOT_DIR` 或 `ROOT_DIR`：项目文件夹的根目录，一般为 `composer.json` 所在目录
- `FRAMEWORK_ROOT_DIR`：使用框架的根目录，自行开发的框架可能会用到，一般框架目录为只读

你可以在你的框架或者 cli 应用程序入口中定义这些常量，以方便在你的项目中使用路径。

下面是 PHP 内置的常量值，在 PHP 解释器内部已被定义：

- `__DIR__`：当前执行脚本的文件所在目录
- `__FILE__`：当前执行脚本的文件路径

### Git 项目模式（source）

Git 项目模式指的是一个框架或程序本身在当前文件夹以纯文本形式存放，运行通过 `php path/to/entry.php` 方式。

假设你的项目存放在 `/home/example/static-php-cli/` 目录下，或你的项目就是框架本身，里面包含 `composer.json` 等项目文件：

```
composer.json
src/App/MyCommand.app
vendor/*
bin/entry.php
```

我们假设从 `src/App/MyCommand.php` 中获取以上常量：

| Constant             | Value                                                |
|----------------------|------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/static-php-cli`                       |
| `SOURCE_ROOT_DIR`    | `/home/example/static-php-cli`                       |
| `FRAMEWORK_ROOT_DIR` | `/home/example/static-php-cli`                       |
| `__DIR__`            | `/home/example/static-php-cli/src/App`               |
| `__FILE__`           | `/home/example/static-php-cli/src/App/MyCommand.php` |

这种情况下，`WORKING_DIR`、`SOURCE_ROOT_DIR`、`FRAMEWORK_ROOT_DIR` 的值是完全一致的：`/home/example/static-php-cli`。
框架的源码和应用的源码都在当前路径下。

### Vendor 库模式（vendor）

Vendor 库模式一般是指你的项目为框架类或者被其他应用作为 composer 依赖项安装到项目中，存放位置在 `vendor/author/XXX` 目录。

假设你的项目是 `crazywhalecc/static-php-cli`，你或其他人在另一个项目使用 `composer require` 安装了这个项目。

我们假设 static-php-cli 中包含同 `Git 模式` 的除 `vendor` 目录外的所有文件，并从 `src/App/MyCommand` 中获取常量值，
目录常量应该是：

| Constant             | Value                                                                                |
|----------------------|--------------------------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/another-app`                                                          |
| `SOURCE_ROOT_DIR`    | `/home/example/another-app`                                                          |
| `FRAMEWORK_ROOT_DIR` | `/home/example/another-app/vendor/crazywhalecc/static-php-cli`                       |
| `__DIR__`            | `/home/example/another-app/vendor/crazywhalecc/static-php-cli/src/App`               |
| `__FILE__`           | `/home/example/another-app/vendor/crazywhalecc/static-php-cli/src/App/MyCommand.php` |


这里的 `SOURCE_ROOT_DIR` 就指的是使用 `static-php-cli` 的项目的根目录。

### Git 项目 Phar 模式（source-phar）

Git 项目 Phar 模式指的是将 Git 项目模式的项目目录打包为一个 `phar` 文件的模式。我们假设 `/home/example/static-php-cli` 将打包为一个 Phar 文件，目录有以下文件：

```
composer.json
src/App/MyCommand.app
vendor/*
bin/entry.php
```

打包为 `app.phar` 并存放到 `/home/example/static-php-cli` 目录下时，此时执行 `app.phar`，假设执行了 `src/App/MyCommand` 代码，常量在该文件内获取：

| Constant             | Value                                                                |
|----------------------|----------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/static-php-cli`                                       |
| `SOURCE_ROOT_DIR`    | `phar:///home/example/static-php-cli/app.phar/`                      |
| `FRAMEWORK_ROOT_DIR` | `phar:///home/example/static-php-cli/app.phar/`                      |
| `__DIR__`            | `phar:///home/example/static-php-cli/app.phar/src/App`               |
| `__FILE__`           | `phar:///home/example/static-php-cli/app.phar/src/App/MyCommand.php` |

因为在 phar 内读取自身 phar 的文件需要 `phar://` 协议进行，所以项目根目录和框架目录将会和 `WORKING_DIR` 不同。

### Vendor 库 Phar 模式（vendor-phar）

Vendor 库 Phar 模式指的是你的项目作为框架安装在其他项目内，存储于 `vendor` 目录下。

我们假设你的项目目录结构如下：

```
composer.json # 当前项目的 Composer 配置文件
box.json # 打包 Phar 的配置文件
another-app.php # 另一个项目的入口文件
vendor/crazywhalecc/static-php-cli/* # 你的项目被作为依赖库
```

将该目录 `/home/example/another-app/` 下的这些文件打包为 `app.phar` 时，对于你的项目而言，下面常量的值应为：

| Constant             | Value                                                                                                |
|----------------------|------------------------------------------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/another-app`                                                                          |
| `SOURCE_ROOT_DIR`    | `phar:///home/example/another-app/app.phar/`                                                         |
| `FRAMEWORK_ROOT_DIR` | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli`                       |
| `__DIR__`            | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli/src/App`               |
| `__FILE__`           | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli/src/App/MyCommand.php` |
