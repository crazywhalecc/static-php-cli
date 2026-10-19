# 贡献指南

StaticPHP 欢迎修复、Package 定义、平台支持、测试和文档贡献。v3 的部分扩展 API 仍在定型，因此需要区分对内置 `core` Registry 的贡献，以及依赖长期兼容契约的外部 Registry。

## 开发环境

检出源码需要 PHP 8.4 或更高版本及 Composer。CLI 还需要[源码安装指南](/zh/guide/installation)中列出的 PHP 扩展。在仓库根目录安装开发依赖：

```bash
composer install
bin/spc --version
```

只有修改 VitePress 文档时才需要 Node.js 和 npm：

```bash
npm install
npm run docs:build
```

尝试真实构建前，请运行 `bin/spc doctor`。完整静态构建依赖平台且可能耗时较长，因此不是每个纯文档、测试或配置改动的前置条件。

## 选择正确的层级

应把改动放在能够表达需求的最小层级：

| 区域 | 用途 |
|---|---|
| `config/pkg/ext/` | PHP 扩展 Package 定义 |
| `config/pkg/lib/` | 链接期依赖库定义 |
| `config/pkg/target/` | 最终和虚拟构建 target |
| `config/pkg/tool/` | 宿主机侧构建工具 Package |
| `config/artifact/` | 共享或复杂的源码/二进制 Artifact 定义 |
| `src/Package/` | Package 专属 recipe、patch、hook 和自定义 Artifact 行为 |
| `src/StaticPHP/` | 框架、resolver、runtime、Doctor、Registry 和命令实现 |
| `tests/` | PHPUnit 测试和 fixture |
| `docs/en/`、`docs/zh/` | 英文规范文档及其同步的中文版本 |

优先使用声明式 YAML 字段，不要用 PHP 条件代替。只有 Package 需要构建命令、源码改写、校验、自定义 configure 参数或 lifecycle hook 时，才增加 recipe class。

不要通过编辑生成的 `buildroot/`、`source/`、`downloads/` 或 `pkgroot/` 内容修复问题。这些改动会被丢弃，也无法说明其他用户如何复现构建。

## 添加或更新 Package

编写新定义前，先搜索使用相同构建系统和 Artifact 类型的 Package，沿用其结构，然后检查：

1. 选择正确类型：`php-extension`、`library`、`target`、`virtual-target` 或 `tool`。
2. 使用精确的 Package 名。PHP 扩展依赖和配置使用 `ext-` 前缀。
3. 硬依赖放在 `depends`，可选关系放在 `suggests`，宿主机构建工具放在 `tools`。
4. Schema 支持时，通过 `@windows`、`@unix`、`@linux` 或 `@macos` 字段表达平台差异。
5. 单个 Package 的简单源码优先使用内联 Artifact；多个 Package 共用，或自定义行为更适合独立定义时，使用 `config/artifact/`。
6. 在 Artifact metadata 中记录许可证标识和文件。只有需要在源码构建后复制许可证内容时，才增加结构化 Package `license` 条目。
7. 只有配置不足以表达行为时，才增加或修改相应 PHP recipe。

当前 schema 参见 [Package 模型](/zh/develop/package-model)和 [Artifact 模型](/zh/develop/artifact-model)。

配置校验会严格拒绝未知字段，但无法证明 URL 存在、patch 仍可应用，或库能在所有平台链接。条件允许时，应测试受影响的最旧和最新 PHP 分支，以及本次改动涉及的平台。

## PHP 代码和测试

StaticPHP 遵循仓库现有代码风格，并优先沿用现有 Package/构建模式，而不是增加新抽象。修改框架代码时：

- 内部编排细节应保持为内部实现；PHP 的 `public` 可见性本身不代表受支持的扩展 API。
- 为 resolver、config、Registry、命令或工具行为增加针对性的 PHPUnit 测试。
- 把失败输入或回归场景写入测试，不要只依赖一次手动构建。
- 不要在同一 PR 中混入无关格式化或机械改动。

测试位于 `tests/StaticPHP/`，使用 `Tests\StaticPHP\` namespace。开发时先运行最小相关测试，提交前再执行项目检查。

## Doctor 改动

内置 Doctor check 当前位于 `src/StaticPHP/Doctor/Item/`。Check 应只观察环境，不修改它；安装、下载和文件写入应放在单独的 fix callback 中。

当前 Attribute 和 callback 签名属于内部实现，并有原始 fix 参数、修复后不自动重查等已知限制。维护内置检查时可以沿用现有 core check，但不能把该模式描述为稳定的第三方 API。用户可见的 check、fix、前置条件或 lock 行为变化时，应更新 [Doctor 环境检查](/zh/develop/doctor-module)和[编译工具](/zh/develop/system-build-tools)。

## 文档

`docs/en/` 下的英文是规范版本。每个英文 Markdown 文件都必须在 `docs/zh/` 下有对应文件；两个版本必须拥有相同的标题、示例、表格、admonition，并链接到相应语言路径。

行为变化时，应更新所有描述该行为的页面，而不只是最接近的参考页。常见情况包括 CLI 选项、环境变量、Package/Artifact 字段、Doctor 检查和迁移说明。

验证文件树一致并构建站点：

```bash
diff <(find docs/en -name '*.md' | sed 's|docs/en/||' | sort) \
     <(find docs/zh -name '*.md' | sed 's|docs/zh/||' | sort)
npm run docs:build
```

新增页面时，还必须同步加入两个 VitePress sidebar。

## 验证清单

根据改动范围运行相应检查：

| 改动 | 必需检查 |
|---|---|
| Package、Artifact、target、extension、library 或 tool 配置 | `bin/spc dev:lint-config`、`composer cs-fix` |
| PHP 框架或 recipe 代码 | `composer cs-fix`、`composer test`、`composer analyse` |
| 文档 | 中英文文件树和结构检查、`npm run docs:build` |
| 平台构建修复 | 上述检查，加上条件允许时最小的受影响构建或 CI matrix |

常用命令：

```bash
bin/spc dev:lint-config
composer cs-fix
composer test
composer analyse
```

本地无法运行完整构建时，应说明哪些平台/PHP 组合尚未测试，并附相关日志或 CI 结果。构建和 shell 日志默认位于 `log/`，但通常不应提交生成的日志。

## Pull Request

每个 Pull Request 应保持聚焦，并说明：

- 问题是什么，以及为什么选择当前修改层级。
- 受影响的 Package/Artifact、目标 OS、架构、toolchain 和 PHP 版本。
- 用户可见行为或兼容性影响。
- 已运行的测试和构建，包括尚未测试的部分。
- 已同步更新的两种语言文档。

完成仓库的 Pull Request checklist。不要提交生成的构建目录、下载归档、缓存、本地环境文件或无关的编辑器/操作系统元数据。

新增依赖或随附 patch 时，应包含上游来源、许可证和需要该内容的条件。优先推动上游修复，并将下游 patch 限制在受影响版本。

## 安全问题报告

不要在普通 issue 中公开漏洞利用细节、凭据、签名材料或可复现的漏洞。仓库支持时请使用 GitHub private vulnerability reporting，或通过现有项目渠道联系维护者，安排私下披露。

仓库目前没有专用 `SECURITY.md`；补充正式的支持版本和披露策略仍是项目维护事项。
