# Registry 与插件系统

<!-- TODO: spc.registry.yml 结构说明。
  通过 SPC_REGISTRIES 环境变量添加外部 Registry。
  Vendor 特定配置、覆盖核心包。Registry 解析顺序与冲突规则。 -->
## 概述

**Registry（注册表）** 是 StaticPHP 的核心扩展机制。你可以把它理解成一个"插件包"：一个 Registry 由一个声明文件（`spc.registry.yml`）和它所指向的配置文件、PHP 类共同组成，描述了一组包的定义（YAML 配置）和对应的构建逻辑（PHP 类）。构建系统在启动时会加载所有已注册的 Registry，将它们的包定义合并后用于整个构建流程。

StaticPHP 本身携带一个内置的核心注册表（`core`），其中包含了 PHP 及相关扩展、库、构建工具等的全部定义。`core` 注册表的声明文件即项目根目录下的 `spc.registry.yml`，它描述了配置文件目录（`config/pkg/`、`config/artifact/`）和构建类的 PSR-4 命名空间（`src/Package/`）之间的映射关系。

外部 Registry 只能定义 `core` 中尚不存在的新包，不能覆盖或修改核心注册表中已有的定义。根据你的需求，有以下三种方式来扩展或修改 StaticPHP 的构建能力：

- **修改 `core` 注册表**：直接修改 `src/Package` 和 `config/pkg/` 下的文件，适用于希望将改动合并回 StaticPHP 主线的情况。请先阅读 [贡献指南](../contributing/) 中关于贡献新包的部分，再提交 PR。
- **Vendor 模式**：将自定义包封装为一个独立的子注册表，以 Composer 包的形式分发，适用于需要私有包或希望以库的形式复用构建逻辑的场景。详见 [Vendor 模式](./vendor-mode/)。
- **外部注册表（`SPC_REGISTRIES`）**：通过环境变量 `SPC_REGISTRIES` 指定一个或多个外部注册表文件的路径，StaticPHP 会在启动时加载它们。适用于临时扩展或不便打包为 Composer 包的场景，与其他包管理器的外部源机制类似。

## Registry 定义文件

每个 Registry 都有一个声明文件，通常命名为 `spc.registry.yml`，位于项目根目录或 Composer 包的根目录下。文件格式支持 YAML（`.yml` / `.yaml`）和 JSON（`.json`）。文件中所有路径均相对于声明文件自身所在目录解析。

StaticPHP 在源码模式（直接 git clone）下，会默认加载项目根目录下的 `spc.registry.yml` 作为核心注册表（`core`）。在 Vendor 模式下，会自动检测当前 Composer 包根目录下是否存在 `spc.registry.yml`，如果存在则加载为一个独立的注册表。通过 `SPC_REGISTRIES` 环境变量指定的外部注册表也必须包含一个有效的声明文件。

下面是一个包含所有可用字段的完整示例（参照 `core` 注册表）：

```yaml
# [必填] 注册表唯一名称，重复加载同名注册表时会自动跳过
name: my-registry

# [可选] Composer autoload 文件路径，外部注册表有自己的依赖时使用
autoload: vendor/autoload.php

# 包（library / php-extension / target）相关配置
package:
  # 包的 YAML 配置文件目录或具体文件路径，可以是数组
  config:
    - config/pkg/lib/
    - config/pkg/target/
    - config/pkg/ext/
  # 包构建类的 PSR-4 命名空间 → 目录路径映射，加载器会扫描目录下所有 PHP 类
  psr-4:
    Package: src/Package
  # 也可以按需加载指定的类，支持数组格式或 {"类名": "文件路径"} 映射格式
  # classes:
  #   - Package\Library\MyLib
  #   MyLib: src/Package/Library/MyLib.php

# 构建产物（Artifact）相关配置
artifact:
  # Artifact 的 YAML 配置文件目录或具体文件路径
  config:
    - config/artifact/
  # Artifact 自定义下载/解压类的 PSR-4 命名空间 → 目录路径映射
  psr-4:
    Package\Artifact: src/Package/Artifact
  # classes: ...（同 package.classes 格式）

# Doctor 环境检查项配置
doctor:
  # Doctor 检查项类的 PSR-4 命名空间 → 目录路径映射
  psr-4:
    StaticPHP\Doctor\Item: src/StaticPHP/Doctor/Item
  # classes: ...（同 package.classes 格式）

# 额外的 CLI 命令配置
command:
  # 自定义命令类的 PSR-4 命名空间 → 目录路径映射
  psr-4:
    Package\Command: src/Package/Command
  # classes: ...（同 package.classes 格式）
```

各顶层字段说明：

| 字段 | 必填 | 说明 |
|---|---|---|
| `name` | ✅ | 注册表唯一名称，重复加载同名注册表时自动跳过 |
| `autoload` | | Composer autoload 文件路径，适用于外部注册表携带自己的依赖时 |
| `package` | | 包定义，含 YAML 配置（`config`）和构建类（`psr-4` / `classes`） |
| `artifact` | | Artifact 定义，含 YAML 配置（`config`）和自定义类（`psr-4` / `classes`） |
| `doctor` | | Doctor 检查项定义，仅含类加载（`psr-4` / `classes`） |
| `command` | | 额外的 CLI 命令定义，仅含类加载（`psr-4` / `classes`） |

其中 `psr-4` 和 `classes` 的区别：`psr-4` 会扫描整个目录下所有符合命名空间规则的 PHP 类并批量注册；`classes` 则用于精确指定某几个类，支持纯数组格式（`["ClassName"]`，需已在 autoload 中可用）或键值映射格式（`{"ClassName": "path/to/file.php"}`，加载器会自动 `require` 对应文件）。
