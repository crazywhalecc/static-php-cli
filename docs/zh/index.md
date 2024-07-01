---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "static-php-cli"
  tagline: "在 Linux、macOS、FreeBSD、Windows 上与 PHP 项目一起构建独立的 PHP 二进制文件，并包含流行的扩展。"
  actions:
    - theme: brand
      text: 指南
      link: ./guide/

features:
- title: 静态二进制
  details: 您可以轻松地编译一个独立的 PHP 二进制文件以供嵌入程序使用。包括 cli、fpm、micro。
- title: phpmicro 自执行二进制
  details: 您可以使用 micro SAPI 编译一个自解压的可执行文件，并将 PHP 代码与二进制文件打包为一个文件。
- title: 依赖管理
  details: static-php-cli 附带依赖项管理，支持安装不同类型的 PHP 扩展和不同的依赖库。
---
