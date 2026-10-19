# Doctor 环境检查

Doctor 用于诊断当前宿主机、选定 target 和编译器 toolchain 能否运行 StaticPHP 构建流水线。它会检查通用宿主机命令、平台专属 SDK、受 StaticPHP 管理的 Tool Package，以及 Zig、musl 等与所选目标相关的前置条件。

## 运行 Doctor

安装 StaticPHP、修改 target/toolchain 或更新系统构建工具后，应显式运行 Doctor：

```bash
# 预构建二进制
./spc doctor

# 源码安装
bin/spc doctor
```

通过 `--auto-fix`（`-y`）选择修复策略：

| 调用方式 | 当前行为 |
|---|---|
| `spc doctor` | 每个可自动修复的项目执行前都询问 |
| `spc doctor --auto-fix` | 不询问，直接执行可用修复 |
| `spc doctor --auto-fix=never` | 报告第一个未解决的失败，不尝试修复 |

只有每个适用检查都通过、被跳过，或者返回了当前实现认为成功的修复时，Doctor 才会成功退出。它会在第一个未解决的失败处停止。

`craft` 命令默认也会运行 Doctor，并使用不询问的自动修复策略；可以在 `craft.yml` 中设置 `craft-options.doctor: false` 禁用该步骤。

## 执行模型

当前执行顺序如下：

1. `DoctorLoader` 收集内置 class 和 Registry 提供的 class，扫描其 public method 上的 Doctor Attribute。
2. 按 `level` 从高到低排序检查。
3. 通过 `#[OptionalCheck]`、`limit_os` 和 `SPC_SKIP_DOCTOR_CHECK_ITEMS` 排除不适用检查。
4. 直接调用 check callback。
5. `null` 表示跳过；`CheckResult::ok()` 表示通过；`CheckResult::fail()` 描述失败，并可指定一个 fix。
6. 根据修复策略，Doctor 会拒绝、询问或立即通过 `ApplicationContext` 调用指定 fix。

`checkAll()` 采用 fail-fast：它不会收集全部失败后再输出汇总。Fixer 成功后，当前实现也不会重新运行原 check。

::: warning
无效的 check 返回值当前会显示“Skipped due to invalid return value”，并被当作成功。这是实现限制，扩展作者不应依赖该行为。
:::

## 内置检查分组

实际检查清单随所选 target 变化。当前 core Registry 提供以下分组：

| 范围 | 检查内容 |
|---|---|
| 通用 | 至少加载了一个 Registry；宿主 OS 是 Linux、macOS 或 Windows |
| Linux | 发行版构建工具基线、CMake 3.22+、`re2c` 1.0.3+，以及 musl 发行版的 Linux headers |
| macOS | Homebrew 或 MacPorts、必需构建命令、GNU Bison 3+，以及选定的 Homebrew/MacPorts LLVM 变体 |
| Windows target | `vswhere`、Visual Studio C++ 工具、Git `patch.exe`、受管理的 MSYS2、`7za.exe` 和初始化后的 MSVC 命令环境 |
| Unix target | 由 StaticPHP 管理且可以正常执行的 `pkg-config` |
| Zig toolchain | 已安装 StaticPHP 的 `zig` Tool Package |
| musl target/toolchain | 所需的 musl wrapper；旧式 `MuslToolchain` 还需要 `/usr/local/musl` 下的交叉工具链 |

Package 通过 `tools` 字段声明的专用工具由 `PackageInstaller` 解析，不会全部重复列入 Doctor 基线检查。各平台软件包清单和 toolchain 细节参见[编译工具](./system-build-tools)。

## 自动修复

根据失败项目和平台，fix 可能会：

- 调用 `apt`、`apk`、`dnf`、`yum`、`pacman`、Homebrew 或 MacPorts。
- 使用 `sudo` 安装系统级内容。
- 下载并安装 StaticPHP Tool 或 Artifact Package。
- 构建 `re2c` 等替代工具。
- 在工作区之外安装 musl runtime 或交叉工具链文件。

因此，自动修复可能访问网络并修改宿主系统。默认交互策略会先询问；CI 通常在一次性环境中使用 `--auto-fix`，或在预配置镜像中使用 `--auto-fix=never`。

Fixer 之间没有共享事务或回滚机制。Fixer 返回 `true` 后，当前实现即认为修复成功，即使环境仍不满足原条件。需要确认时，应在修复后重新运行 `spc doctor --auto-fix=never`。

## Doctor Lock

所有检查被视为成功后，Doctor 会写入 `.spc-doctor.lock`，内容为当前 StaticPHP 版本。`craft` 的 Doctor 步骤成功后也会写入同一 lock。

首选位置如下：

| 平台 | 首选路径 | 回退位置 |
|---|---|---|
| Unix | `$XDG_CACHE_HOME/.spc-doctor.lock`，或 `$HOME/.cache/.spc-doctor.lock` | 系统临时目录，然后是工作目录 |
| Windows | `%LOCALAPPDATA%\.spc-doctor.lock` | 系统临时目录，然后是工作目录 |

调用 `checkDoctorCache()` 的构建/下载命令只使用该 lock 决定是否显示警告。Lock 不会跳过显式的 `spc doctor`，也不会阻止构建。

当前 fingerprint 只有 StaticPHP 版本。target、toolchain、Registry 集合、PATH 或操作系统发生变化时，lock 不会自动失效。此类变化后应删除 lock 或显式运行 Doctor。

## 跳过检查和警告

两个环境变量用途不同：

```ini
# 在实际 Doctor 运行中跳过指定项目（使用完整项目名，逗号分隔）
SPC_SKIP_DOCTOR_CHECK_ITEMS="if cmake version >= 3.22,if re2c version >= 1.0.3"

# Doctor lock 缺失或过期时，不显示构建前警告
SPC_SKIP_DOCTOR_CHECK=1
```

`SPC_SKIP_DOCTOR_CHECK` 不会从显式 `spc doctor` 中移除检查。`SPC_SKIP_DOCTOR_CHECK_ITEMS` 会；跳过必要检查可能让不可用的环境看起来健康，因此只应在受控 CI 或调试场景中使用。

## 当前内部注册模型

Registry 当前可以通过 `doctor.psr-4` 或 `doctor.classes` 指向 PHP class。`DoctorLoader` 会以无参数构造这些 class，并识别：

| Attribute | 当前作用 |
|---|---|
| `#[CheckItem]` | 注册检查名、可选 OS 限制和数字 level；level 越高越先执行 |
| `#[OptionalCheck]` | 提供 class 或 method 级 callable，决定该检查是否存在 |
| `#[FixItem]` | 注册字符串 fix 名，由失败的 `CheckResult` 引用 |

Check callback 当前不接收 DI context，返回 `null` 或 `CheckResult`。Fix callback 通过 `ApplicationContext` 调用，并把 `CheckResult` 的原始 `fix_params` 数组作为 callback context。`CheckItem` 的 `manual` 属性当前未被执行器使用。
