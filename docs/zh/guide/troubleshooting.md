# 故障排除

使用 StaticPHP 过程中可能会碰到各种各样的故障，这里将讲述如何自行查看错误并反馈 Issue。

## 下载失败问题

下载资源问题是 spc 最常见的问题之一。主要是由于 spc 下载资源使用的地址一般均为对应项目的官方网站或 GitHub 等，而这些网站可能偶尔会宕机、屏蔽 IP 地址。
在遇到下载失败后，可以多次尝试调用下载命令。

当下载资源时，你可能最终会看到类似 `curl: (56) The requested URL returned error: 403` 的错误，这通常是由于 GitHub 限制导致的。
你可以通过在命令中添加 `-vvv` 来验证，会看到类似 `[DEBU] Running command (no output) : curl -sfSL   "https://api.github.com/repos/openssl/openssl/releases"` 的输出。

要解决这个问题，可以在 GitHub 上 [创建](https://github.com/settings/tokens) 一个个人访问令牌，并将其设置为环境变量 `GITHUB_TOKEN=<XXX>`。

如果确认地址确实无法正常访问，可以提交 Issue 或 PR 更新地址或下载类型。

## 通过代理下载

如果你的网络需要通过代理才能访问下载源，可以直接设置 `ALL_PROXY`，也可以使用 `SPC_PROXY`：

```shell
# curl 和 git 本来就会读取 ALL_PROXY
ALL_PROXY="socks5h://127.0.0.1:1080" spc download --for-extensions="curl,openssl"

# SPC_PROXY 额外覆盖 Go 工具链（FrankenPHP），因为 Go 会忽略 ALL_PROXY
SPC_PROXY="socks5h://127.0.0.1:1080" spc build:php "curl" --build-cli
```

`socks5h` 表示由代理端解析域名，通常这是你需要的模式。
StaticPHP 会把它作为 `ALL_PROXY` 传给 curl 和 git，并作为 `HTTP_PROXY`/`HTTPS_PROXY` 传给 Go
（把 `socks5h://` 改写为 `socks5://`，这是 Go 唯一支持的 SOCKS 形式）。

如需长期生效，可将其写入 `config/env.ini` 或 `config/env.custom.ini`：

```ini
[global]
SPC_PROXY="socks5h://127.0.0.1:1080"
```

## Doctor 无法修复某些问题

在绝大部分情况下，doctor 模块都可以对缺失的系统环境进行自动修复和安装，但也存在特殊的环境无法正常使用自动修复功能。

由于系统限制（例如，Windows 下无法自动安装 Visual Studio 等软件），自动修复功能无法用于某些项目。
在遇到无法自动修复功能时，如果遇到 `Some check items can not be fixed` 字样，则表明无法自动修复。
请根据终端显示的方法提交 Issue 或自行修复环境。

## 编译错误

遇到编译错误时，如果没有开启 `-vvv` 日志，请先开启调试日志，然后确定报错的命令。
报错的终端输出对于修复编译错误非常重要。
在提交 Issue 时，请上传终端日志的最后报错片段（或整个终端日志输出），并且包含使用的 `spc` 命令和参数。

如果你是重复构建，请参考 [首次构建 - 调试与重新构建](./first-build#调试与重新构建) 章节。

