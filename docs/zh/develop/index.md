# 开发简介

开发本项目需要安装部署 PHP 环境，以及一些 PHP 项目常用的扩展和 Composer。

项目的开发环境和运行环境几乎完全一致，你可以参照 **指南-本地构建** 部分安装系统 PHP 或使用本项目预构建的静态 PHP 作为环境，这里不再赘述。

抛开用途，本项目本身其实就是一个 `php-cli` 程序，你可以将它当作一个正常的 PHP 项目进行编辑和开发，同时你需要了解不同系统的 Shell 命令行。

本项目目前的目的就是为了编译静态编译的独立 PHP，但主体部分也包含编译很多依赖库的静态版本，所以你可以复用这套编译逻辑，用于构建其他程序的独立二进制版本，例如 Nginx 等。

## 环境准备

开发本项目需要 PHP 环境。你可以使用系统自带的 PHP，也可以使用本项目构建的静态 PHP。

无论是使用哪种 PHP，在开发环境，你需要安装这些扩展：

```
curl,dom,filter,mbstring,openssl,pcntl,phar,posix,sodium,tokenizer,xml,xmlwriter
```

static-php-cli 项目本身不需要这么多扩展，但在开发过程中，你会用到 Composer、PHPUnit 等工具，它们需要这些扩展。

> 对于 static-php-cli 自身构建的 micro 自执行二进制，仅需要 `pcntl,posix,mbstring,tokenizer,phar`。

## 开始开发

继续向下查看项目结构的文档，你可以从中了解 `static-php-cli` 是如何运作的。
