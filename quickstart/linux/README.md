# 快速启动容器环境

> 提供了 debian 11 构建 和 alpine 构建环境

> 任意选一个就可以 

## debian 11 构建环境

```bash

# 启动 debian 11 容器环境
sh quickstart/linux/run-debian-container.sh

# 进入容器 
sh quickstart/linux/connection-static-php-cli.sh

# 准备构建基础软件
sh quickstart/linux/debian-init.sh
# 准备构建基础软件 使用镜像 
sh quickstart/linux/debian-init.sh --mirror china 

```

## aline 构建环境

```bash

# 启动 alpine 容器环境
sh quickstart/linux/run-alpine-container.sh

# 进入容器 
sh quickstart/linux/connection-static-php-cli.sh

# 准备构建基础软件
sh quickstart/linux/alpine-init.sh
# 准备构建基础软件 使用镜像
sh quickstart/linux/alpine-init.sh --mirror china 

```