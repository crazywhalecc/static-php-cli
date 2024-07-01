# Action 构建

Action 构建指的是直接使用 GitHub Action 进行编译。

如果你不想自行编译，可以从本项目现有的 Action 下载 Artifact，也可以从自托管的服务器下载：[进入](https://dl.static-php.dev/static-php-cli/common/)

> 自托管的二进制也是由 Action 构建而来，[项目仓库地址](https://github.com/static-php/static-php-cli-hosted)。

## 构建方法

使用 GitHub Action 可以方便地构建一个静态编译的 PHP 和 phpmicro，同时可以自行定义要编译的扩展。

1. Fork 本项目。
2. 进入项目的 Actions，选择 CI 开头的 Workflow（根据你需要的操作系统选择）。
3. 选择 `Run workflow`，填入你要编译的 PHP 版本、目标类型、扩展列表。（扩展列表使用英文逗号分割，例如 `bcmath,curl,mbstring`）
4. 等待大约一段时间后，进入对应的任务中，获取 `Artifacts`。

如果你选择了 `debug`，则会在构建时输出所有日志，包括编译的日志，以供排查错误。

> 如果你需要在其他环境构建，可以使用 [手动构建](./manual-build)。

## 扩展选择

你可以到 [扩展列表](./extensions) 中查看目前你需要的扩展是否均支持，
然后到 [在线命令生成](./cli-generator) 中选择你需要编译的扩展，复制扩展字符串到 Action 的 `extensions` 中，编译即可。
