# 项目结构

## 概念

StaticPHP 本身是一个基于 `symfony/console` 的 CLI 应用，核心代码位于 `src/StaticPHP` 目录下。
它主要分为几个模块：

- **Registry**：负责管理注册表数据，每个注册表含有多个包（Package），StaticPHP 项目本身内置一个 `core` 注册表，包含 PHP 及相关扩展、依赖等。
- **Package**：代表一个包，包的种类有四种：`php-extension`（PHP 扩展）、`library`（库）、`target`（构建目标）、`virtual-target`（虚构建目标）。每个包包含构建信息、依赖关系等。
- **Installer/Builder**：负责处理包的安装和构建逻辑，调用构建命令、解压构建产物、处理构建结果等。
- **Doctor**：提供系统环境检查功能，负责安装和检查系统层面依赖的工具、需要的文件等，如 `make`、`cmake`、`autoconf` 等。
- **Runtime/Executor**：包含运行时相关的工具类，如执行 shell 命令、执行 CMake 构建等。
- **Toolchain**：对不同操作系统及环境，提供对应系统的工具链抽象接口，负责处理构建过程中与系统环境相关的差异。
- **Utils**：一些通用的工具类，如文件系统操作、日志记录、操作系统相关助手方法等。
- **DependencyResolver**：负责解析包之间的依赖关系，生成构建顺序等。

## 目录结构

```
static-php-cli/
├── bin/                        # 可执行入口脚本（spc、spc.ps1、setup-runtime 等）
├── config/
│   ├── env.ini                 # 默认环境变量配置
│   ├── env.custom.ini          # 用户自定义环境变量（覆盖 env.ini）
│   ├── artifact/               # 构建产物配置（下载工具链、预构建二进制等）
│   └── pkg/                    # 包配置文件（YAML）
│       ├── ext/                # PHP 扩展包配置（ext-*.yml、builtin-extensions.yml）
│       ├── lib/                # 库包配置（*.yml）
│       └── target/             # 构建目标配置（php.yml、curl.yml 等）
├── src/
│   ├── bootstrap.php           # 应用引导（注册自动加载、DI 容器等）
│   ├── globals/                # 全局辅助函数
│   ├── Package/                # 各包的构建逻辑实现（PHP 类）
│   │   ├── Artifact/           # 构建产物的自定义下载/解压逻辑
│   │   ├── Command/            # 包级别自定义命令
│   │   ├── Extension/          # PHP 扩展构建类（ext-*.php）
│   │   ├── Library/            # 库构建类（*.php）
│   │   └── Target/             # 构建目标类（php.php、curl.php 等）
│   └── StaticPHP/              # 框架核心代码
│       ├── ConsoleApplication.php  # Symfony Console 应用入口
│       ├── Artifact/           # 构建产物下载与解压（Downloader、Extractor 等）
│       ├── Attribute/          # PHP 注解定义
│       │   ├── Artifact/       # 产物相关注解（CustomSource、BinaryExtract 等）
│       │   ├── Doctor/         # Doctor 相关注解（CheckItem、FixItem 等）
│       │   └── Package/        # 包构建相关注解（BuildFor、BeforeStage、AfterStage、
│       │                       #   CustomPhpConfigureArg、PatchBeforeBuild 等）
│       ├── Command/            # CLI 命令实现（build-libs、build-target、doctor 等）
│       ├── Config/             # 配置加载与验证（PackageConfig、ArtifactConfig 等）
│       ├── DI/                 # 依赖注入容器（ApplicationContext、CallbackInvoker）
│       ├── Doctor/             # 系统环境检查与修复（Doctor、CheckResult）
│       ├── Exception/          # 自定义异常类
│       ├── Package/            # 包核心模型与构建调度
│       │   ├── Package.php             # 包基类
│       │   ├── LibraryPackage.php      # 库包类型
│       │   ├── PhpExtensionPackage.php # PHP 扩展包类型
│       │   ├── TargetPackage.php       # 构建目标包类型
│       │   ├── PackageInstaller.php    # 包安装器（下载、解压源码）
│       │   └── PackageBuilder.php      # 包构建器（执行构建流程）
│       ├── Registry/           # 注册表管理（Registry、PackageLoader、ArtifactLoader）
│       ├── Runtime/            # 运行时工具
│       │   ├── Executor/       # 命令执行器（UnixAutoconfExecutor、UnixCMakeExecutor、
│       │   │                   #   WindowsCMakeExecutor、Executor 基类）
│       │   ├── Shell/          # Shell 抽象（UnixShell、WindowsCmd 等）
│       │   └── SystemTarget.php # 系统目标信息
│       ├── Toolchain/          # 工具链抽象（GccNative、Musl、MSVC、Zig、ClangBrew 等）
│       └── Util/               # 通用工具类
│           ├── System/         # 系统平台工具（LinuxUtil、MacOSUtil、WindowsUtil 等）
│           ├── BuildRootTracker.php  # buildroot 文件追踪
│           ├── DependencyResolver.php # 依赖解析与构建顺序
│           ├── FileSystem.php        # 文件系统操作
│           ├── GlobalEnvManager.php  # 全局环境变量管理
│           ├── InteractiveTerm.php   # 交互式终端输出
│           ├── LicenseDumper.php     # 开源协议导出
│           ├── PkgConfigUtil.php     # pkg-config 工具封装
│           ├── SourcePatcher.php     # 源码补丁工具
│           └── SPCConfigUtil.php     # SPC 配置读取工具
├── tests/                      # 单元测试与集成测试
├── downloads/                  # 下载缓存目录（源码包、预构建二进制）
├── source/                     # 解压后的源码目录
├── buildroot/                  # 构建输出目录（头文件、静态库等）
├── pkgroot/                    # 按平台归档的构建产物
└── spc.registry.yml            # core 注册表定义文件
```

需要注意的是，`src/Package` 目录下的类主要负责实现具体包的构建逻辑，而 `src/StaticPHP` 目录下的类则提供了构建框架的核心功能，如命令调度、环境检查、工具链抽象等，两者是解耦的。`src/Package` 对应的是 `core` 注册表中的包，其中包含 PHP 及相关扩展、库、构建目标等的具体实现，而 `src/StaticPHP` 则是整个构建系统的基础设施，支持不同注册表和包的构建需求。
