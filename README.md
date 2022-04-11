# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. [English README](/README-en.md)

编译纯静态的 PHP Binary 二进制文件，带有各种扩展，让 PHP-cli 应用变得更便携！

注：只能编译 CLI 模式，暂不支持 CGI 和 FPM 模式

[![版本](https://img.shields.io/badge/script--version-1.4.0-green.svg)]()
[![License](https://img.shields.io/badge/License-MIT-blue.svg)]()
![Build Actions](https://github.com/crazywhalecc/static-php-cli/actions/workflows/build-php.yml/badge.svg)

## 编译环境需求
- 目前支持 arm64、x86_64、armv7l 架构
- 需要 Docker（也可以直接在 Alpine Linux 上使用）
- 脚本支持编译的 PHP 版本（7.2 ~ 8.1）

## 运行环境需求
Linux

## 开始
可以直接下面的地址下载 Actions 构建的文件。

<https://dl.zhamao.xin/php-bin/file/>

也可以自己使用 Dockerfile 进行编译构建：
```bash
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli/docker
docker build -t static-php . --build-arg USE_BACKUP_ADDRESS=no --build-arg COMPILE_PHP_VERSION=7.4.28
```

编译之后可以使用下方命令将二进制 PHP 提取出来，用以下方式：
```bash
mkdir dist
docker run --rm -v $(pwd)/dist:/dist/ -it static-php cp php-dist/bin/php /dist/
cd dist
file ./php
```

如果要选择安装的扩展，可以修改 `docker/extensions.txt` 文件，具体规则如下：
- 文件内使用 `#` 可以注释，表示不安装
- 扩展名一律使用小写，目前默认状态下文件内所列的扩展为支持的扩展，其他扩展暂不支持，如有需求请提 Issue 添加

## 支持的扩展表
| 是否支持 | PHP 扩展名称 | 支持版本 | 备注                                                    |
| -------- | ------------ | -------- | ------------------------------------------------------- |
| yes, enabled      | bcmath       | *        |                                                         |
| yes, enabled      | calendar     | *        |                                                         |
| yes, enabled      | ctype        | *        |                                                         |
| yes, enabled      | curl         | *        | 自带下载编译 curl 库                                    |
| yes, enabled      | dom          | *        |                                                         |
|          | event        |          |                                                         |
| yes, enabled      | filter       | *        |                                                         |
| yes, enabled      | fileinfo     | *        |                                                         |
| yes, enabled      | gd           | *        |                                                         |
| yes, enabled      | hash         | *        |                                                         |
| yes, enabled      | iconv        | *        |                                                         |
| yes, enabled      | inotify      | 3.0.0    | 从 pecl 或镜像站下载的源码                                |
| yes, enabled      | json         | *        |                                                         |
| yes, enabled      | libxml       | *        | 自带下载编译 libxml2 库                                  |
| yes, enabled      | mbstring     | *        |                                                         |
| yes, enabled      | mongodb      | >=1.9.1  | 未测试，从 pecl 或镜像站下载的源码                        |
|          | mysqli       |          |                                                         |
| yes, enabled      | mysqlnd      | *        |                                                         |
| yes, enabled      | openssl      | *        |                                                         |
| yes, enabled      | pcntl        | *        |                                                         |
| yes, enabled      | pdo          | *        |                                                         |
| yes, enabled      | pdo_mysql    | *        |                                                         |
|          | pdo_pgsql    | *        |                                                         |
| yes, enabled      | phar         | *        |                                                         |
| yes, enabled      | posix        | *        |                                                         |
| yes, enabled      | redis        | *        | 从 pecl 或镜像站下载的源码                                |
| yes, enabled      | simplexml    | *        |                                                         |
| yes, enabled      | sockets      | *        |                                                         |
| yes, enabled      | sqlite3      | *        |                                                         |
| yes, enabled      | swoole       | >=4.6.6  | 使用参数 `--enable-openssl --with-openssl --with-openssl-dir=/usr`，从 pecl 或镜像站下载的源码 |
| yes, enabled      | tokenizer    | *        |                                                         |
| yes, enabled      | xml          | *        |                                                         |
| yes, enabled      | xmlreader    | *        |                                                         |
| yes, enabled      | xmlwriter    | *        |                                                         |
|          | zip          |          | 已经支持，但是脚本搞丢了，需要重新写                       |
| yes, enabled      | zlib         | *        |                                                         |

## 自定义
- `docker/Dockerfile` 中修改 `VER_PHP=x.x.x` 来更换 PHP 版本。
- `docker/Dockerfile` 中修改 `USE_BACKUP=yes` 使用备用下载源码地址（建议**不在中国大陆的用户**使用备用地址）。
- `docker/extensions.txt` 指定要编译安装的扩展。
- `docker/compile-php.sh` 中的 `php_compile_args` 函数来调整 PHP 编译参数。
- `docker/check-extensions.sh` 中的 `check_in_configure` 函数可调整 PHP 扩展编译的参数 。
- `docker/config.json` 可调整要下载的扩展和依赖库版本和链接。
- `docker/fast-compiler.sh` 可以在 Alpine Linux 系统下直接运行。
- `docker/multi-version-compiler.sh` 可以同时编译多个版本的 PHP。

## 目前的问题（对勾为已解决）
- [ ] 不支持 event(libevent) 扩展，event 扩展的 sockets 支持不能在静态编译中使用，因为静态内嵌编译暂时没办法调整扩展编译顺序，同时其本身也不支持静态编译。
- [ ] Swoole 扩展不支持 `--enable-swoole-curl`，也是因为编译顺序和加载顺序的问题。
- [ ] 不支持 readline 扩展，readline 扩展安装后无法正常使用 `php -a`，原因还没有弄清楚，可能是静态编译造成的 ncurses 库出现了问题。
- [X] curl/libcurl 扩展静态编译
- [X] 可自行选择不需要编译进入的扩展
- [ ] php.ini 内嵌或分发
- [X] i18n（国际化脚本本身和 README）
- [ ] 在阿里云的部分阉割系统上可能缺少 openssl 配置，可能会导致 PHP 自身的 openssl 无法正常使用

如果你对以上问题有解决方案，请提出 Issue 或 PR！

如果你对此脚本比较感兴趣，未来会在此编写脚本中涉及内容的解析和说明。

## 运行示例
编译后的状态

![image](https://user-images.githubusercontent.com/20330940/120911319-219b3000-c6b9-11eb-82d1-b4309cfca8b3.png)

直接运行 Workerman

![image](https://user-images.githubusercontent.com/20330940/120911301-f7e20900-c6b8-11eb-99eb-ebc84ab95df0.png)

## 原理

静态编译是一项比较多见于 Golang 的编译方式，在传统的 Linux 系统下，正常的程序和库基本是动态编译链接（Dynamically linked）的，也就是说，不同程序引用同样的库可以共用，减少资源重复。

但是由于不少系统软件环境配置复杂，或者依赖的库版本冲突，一般使用 Docker 等容器技术可以解决这一问题。但 Docker 等容器也需要拉取镜像，体积较大，对于程序有便携需求的人（比如网络安全员做渗透测试等）需要很多程序可以像 Windows 上的绿色程序一样随处打包运行。

PHP 是最好的编程语言，它编写容易，易于部署和开发，倘若将 PHP 编译为静态的文件，并且将 Swoole 或 libevent 等库同样内嵌，那 PHP 不仅将可以编写便携的 Web 服务器，还能做很多想不到的事！

编译静态 PHP 大致分为以下几个步骤：
1. 下载 PHP 源码
2. 下载需要静态编译的额外扩展源码（如 inotify、mongodb、redis 等）
3. 将额外扩展源码放入 PHP 源码中
4. 生成 `configure` 并使用 `-static` 的 FLAG 进行生成 makefile
5. 修改 Makefile 中的编译参数，增加 `-all-static` 和去掉 dynamic 相关的参数
6. 使用 `make` 构建静态 PHP
7. 使用 `make install` 安装到指定目录，再使用 `strip` 去除符号表缩小体积

对于第二步，如果额外扩展中有依赖 Linux 的其他库（比如 curl 依赖 libcurl），则需要在第二步之前编译安装对应库的静态版本（比如 libxml2.a）

而此处出问题最多的部分就是安装额外扩展的依赖上，很多库不支持静态编译，而互联网很难找到对对应库进行静态编译的资料。

脚本和 Dockerfile 统一采用 Alpine 的目的就是，apk 包管理下有很多库提供了 `*-static` 静态版本，直接使用包管理安装就可以使用，而即使没有，也可以使用 musl-libc 进行静态编译，避免 glibc 下的 `libnss` 等无法静态编译的问题。

第二种要面对比较棘手的问题就是 PHP 扩展可能本身不支持静态编译（如 curl 扩展），有些通过绕过手段可以静态编译，但有些只能通过对扩展源码进行修改才能使其支持。

所以这个项目中涉及的脚本，最大的问题就在于对其他依赖的处理，而不是 PHP 编译本身。PHP 如果不启用任何扩展（即使用 `--disable-all`），则可以很方便地静态编译。

## 参考资料
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
