# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. [English README](/README-en.md)

编译纯静态的 PHP Binary 二进制文件，带有各种扩展（CLI 模式，暂不支持 CGI 和 FPM 模式）

[![版本](https://img.shields.io/badge/script--version-1.2.1-green.svg)]()

## 环境需求
- 目前在 x86_64 和 aarch64(arm64) 架构上编译成功，其他架构需自行测试
- 需要 Alpine Linux（测试环境为 3.13 版本，其他版本未测试）系统（也就是说需要 musl）或 Docker（Dockerfile 正在完善）
- WSL2 也是支持的
- 脚本支持编译的 PHP 版本（7.2 ~ 8.0）

## 开始
可以直接在旁边的 Release 中下载编译好的二进制，也可以自己编译。
```bash
# 自己编译
./static-compile-php.sh
# 完事后在 `php-dist/bin/php` 这个二进制文件可以随意拿着去任何一个 Linux 系统运行了！

# 多 PHP 版本一键编译
./multi-version-compile.sh
# 结束后多个 PHP 版本的二进制文件会在 build/ 目录下。
```

## 支持的扩展（对勾为已支持的扩展，未打勾的正在努力兼容）
- [X] bcmath
- [X] calendar
- [X] ctype
- [X] filter
- [X] openssl
- [X] pcntl
- [X] iconv
- [X] inotify
- [X] json
- [X] mbstring
- [X] phar
- [X] curl
- [X] pdo
- [X] gd
- [X] pdo_mysql
- [X] mysqlnd
- [X] sockets
- [X] swoole
- [X] redis
- [X] simplexml
- [X] dom
- [X] xml
- [X] xmlwriter
- [X] xmlreader
- [X] posix
- [X] tokenizer
- [ ] zip

## 目前的问题（待解决的）
- [ ] event 扩展的 sockets 支持不能在静态编译中使用，因为静态内嵌编译暂时没办法调整扩展编译顺序。
- [ ] Swoole 扩展不支持 `--enable-swoole-curl`，也是因为编译顺序和加载顺序的问题。
- [ ] readline 扩展安装后无法正常使用 `php -a`，原因还没有弄清楚，可能是静态编译造成的 ncurses 库出现了问题。

## Todo List
- [X] curl/libcurl 扩展静态编译
- [X] 可自行选择不需要编译进入的扩展
- [ ] php.ini 内嵌或分发
- [ ] i18n（国际化脚本本身和 README）

## 运行示例
编译后的状态
![image](https://user-images.githubusercontent.com/20330940/116291663-6df47580-a7c7-11eb-8df3-6340c6f87055.png)

在不同系统直接运行 Swoft
![image](https://user-images.githubusercontent.com/20330940/116053161-f16d7400-a6ac-11eb-87b8-e510c6454861.png)

## 参考资料
- <https://blog.terrywh.net/post/2019/php-static-openssl/>
- <https://stackoverflow.com/a/37245653>
- <http://blog.gaoyuan.xyz/2014/04/09/statically-compile-php/>
