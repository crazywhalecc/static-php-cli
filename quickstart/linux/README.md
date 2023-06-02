# 快速启动容器环境

> 提供了 debian 11 构建 和 alpine 构建环境
> 任意选一个就可以 

## debian 11 构建环境

```bash

# 启动 debian 11 容器环境
sh quickstart/linux/run-debian-11-container.sh

# 进入容器 
sh quickstart/linux/connection-static-php-cli.sh

# 准备构建基础软件
sh quickstart/linux/debian-11-init.sh

```

## aline 构建环境

```bash

# 启动 alpine 容器环境
sh quickstart/linux/run-alpine-3.16-container.sh

# 进入容器 
sh sh quickstart/linux/connection-static-php-cli.sh

# 准备构建基础软件
sh quickstart/linux/alpine-3.16-init.sh

```