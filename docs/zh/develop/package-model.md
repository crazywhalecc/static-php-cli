# Package 模型

## Package 定义

Package 是 StaticPHP 构建系统中的核心概念，代表一个可构建/可安装的单元，如 PHP 扩展、库、构建目标等。

每个 Package 包含构建信息、依赖关系、构建逻辑等，构成了 StaticPHP 的构建模型。Package 的定义主要通过 YAML/JSON 配置文件来实现。`core` Registry 的 Package 配置位于 `config/pkg/` 目录下，可选 recipe class 位于 `src/Package/` 对应的子目录中。

Package 分为五种类型：

- **php-extension**：PHP 扩展包，包含 PHP 扩展的构建信息和构建逻辑。
- **library**：依赖库 Package，通常安装到 `buildroot/`，供其他 Package 编译和链接。
- **target**：构建目标包，代表最终的构建产物，如 PHP 二进制、curl 二进制等，继承自 `library` 包类型。
- **virtual-target**：虚构建目标包，代表一个抽象的构建目标，不直接对应构建产物，主要用于依赖管理和构建调度。
- **tool**：宿主机侧构建工具 Package，单独安装在 `pkgroot/` 下，不作为链接期库依赖处理。

```yaml
{pkg-name}:
  type: {pkg-type}
  ...
```

## Artifact 定义

Artifact 是独立于 Package 的定义，它包含构建包的源码归档文件或预构建的二进制文件。每个 Artifact 定义了下载 URL、解压方式、构建产物的文件路径等信息。一个 Package 可以通过 `artifact` 字段引用一个 Artifact，以获取构建所需的源码或二进制文件。

简单来说，默认情况下，一个 Package 对应一个 Artifact；如果多个 Package 共用一份源码时，可以定义一个 Artifact 供多个 Package 引用。Artifact 的定义位于 `config/artifact/` 目录下，对应的自定义下载/解压逻辑类位于 `src/Package/Artifact/` 目录下；对于虚拟目标、PHP 内置扩展等特殊包类型，Package 也可以不设置 Artifact 字段。

我们假设 `example-library-package` 是一个依赖库，它的源码归档文件托管在 `https://example.com/example-library.tar.gz`，则它的 Package 定义和 Artifact 定义可以如下所示：

```yaml
example-library-package:
  type: library
  artifact:
    source:
      type: url
      url: 'https://example.com/example-library.tar.gz'
```

更多有关 Artifact 定义的内容，请参阅 [Artifact 模型](./artifact-model) 章节。

## php-extension 包类型

php-extension Package 代表一个 PHP 扩展，它的配置文件位于 `config/pkg/ext/` 目录下。可选的 recipe class 通常放在 `src/Package/Extension/`，通过 `#[Extension]` 注册，并从 callback context 接收对应的 `PhpExtensionPackage`。当前 core recipe 支持继承 `PhpExtensionPackage`，但并不要求继承。

```yaml
ext-lz4:
  type: php-extension
  artifact:
    source:
      type: git
      url: 'https://github.com/kjdev/php-ext-lz4.git'
      rev: master
      extract: php-src/ext/lz4
    metadata:
      license-files: [LICENSE]
      license: MIT
  depends:
    - liblz4
  php-extension:
    arg-type@unix: '--enable-lz4@shared_suffix@ --with-lz4-includedir=@build_root_path@'
    arg-type@windows: '--enable-lz4'
```

`php-extension` 允许的定义字段：

```yaml
ext-{ext-name}:          # 包名必须以 ext- 前缀开头
  type: php-extension

  # ── 通用字段 ─────────────────────────────────────────────────────────────
  description: '...'     # 可选，人类可读的包描述
  lang: c                # 可选，扩展的实现语言（c / c++ 等）
  frameworks: []         # 可选，相关macOS框架依赖列表

  artifact: '{artifact-name}'  # 可选；字符串时引用指定名称的 Artifact 定义，
                               # 对象时为内联 Artifact（内置扩展无需此字段）

  # depends / suggests / tools 支持 @windows / @unix / @linux / @macos 后缀
  depends: []            # 可选，硬依赖列表（库名直接写，PHP 扩展需加 ext- 前缀）
  depends@unix: []       # 可选，仅 Unix 平台生效的硬依赖
  depends@windows: []    # 可选，仅 Windows 平台生效的硬依赖
  suggests: []           # 可选，可选依赖列表（格式同 depends）
  suggests@unix: []
  tools: []              # 可选，宿主机侧构建工具依赖，单独解析
  tools@windows: []      # 可选，特定平台的工具依赖

  # ── php-extension 专属字段（嵌套在 php-extension: 对象中）─────────────────
  php-extension:
    # arg-type 决定传递给 ./configure 的参数形式，支持平台后缀
    # 支持的平台后缀：@unix（Linux + macOS）、@linux、@macos、@windows
    # 优先级（以 Linux 为例）：arg-type@linux > arg-type@unix > arg-type（无后缀）
    # 内置关键字：
    #   enable      → --enable-{extname}（默认值，未配置时使用）
    #   enable-path → --enable-{extname}={buildroot}
    #   with        → --with-{extname}
    #   with-path   → --with-{extname}={buildroot}
    #   custom/none → 不传递任何参数（由 PHP 类的 #[CustomPhpConfigureArg] 方法处理）
    # 也可直接写完整参数字符串，支持以下占位符：
    #   @build_root_path@   → BUILD_ROOT_PATH（buildroot 绝对路径）
    #   @shared_suffix@     → 共享构建时展开为 =shared，静态构建时为空
    #   @shared_path_suffix@ → 共享构建时展开为 =shared,{buildroot}，静态构建时为 ={buildroot}
    arg-type: enable
    arg-type@unix: '--enable-my-extension@shared_suffix@'
    arg-type@windows: with-path

    zend-extension: false  # 可选，true 表示这是 Zend 扩展（如 opcache、xdebug）
    build-shared: true     # 可选，是否允许构建为共享扩展（.so），默认 true
    build-static: true     # 可选，是否允许内联静态构建（编译进 PHP），默认 true
    build-with-php: true   # 可选，true 表示该扩展通过 PHP 源码树一同编译（内置扩展使用）

    # display-name 影响 smoke test 中 php --ri 的参数及许可证导出显示名称
    # 不填时默认使用扩展名（ext- 后缀部分）；填空字符串则跳过 --ri 检查
    display-name: 'My Extension'

    # os 限制该扩展仅在指定平台上可用，不在列表内的平台会拒绝构建
    # 可选值：Linux、Darwin、Windows
    os: [Linux, Darwin]
```

## library 包类型

library Package 代表 openssl、zlib 等依赖库，可以从源码或预构建二进制安装。其配置文件位于 `config/pkg/lib/` 目录下。可选的 recipe class 通常放在 `src/Package/Library/`，通过 `#[Library]` 注册，并从 callback context 接收 `LibraryPackage`；不要求继承该类。

以 openssl 为例：

```yaml
openssl:
  type: library
  artifact:
    source:
      type: ghrel
      repo: openssl/openssl
      match: openssl.+\.tar\.gz
      prefer-stable: true
    binary: hosted
    metadata:
      license-files: [LICENSE.txt]
      license: OpenSSL
  depends:
    - zlib
  depends@windows:
    - zlib
    - jom
  headers:
    - openssl
  static-libs@unix:
    - libssl.a
    - libcrypto.a
  static-libs@windows:
    - libssl.lib
    - libcrypto.lib
```

`library` 允许的定义字段：

```yaml
{lib-name}:
  type: library           # library 或 target（target 继承 library 的所有字段）

  # ── 通用字段 ─────────────────────────────────────────────────────────────
  description: '...'     # 可选，人类可读的包描述
  license:               # 可选，从源码构建后复制的许可证内容
    type: file           # type 可为 file 或 text，也可填写多个条目的列表
    path: LICENSE
  lang: c                # 可选，库的实现语言（c / c++ 等）
  frameworks: []         # 可选，相关框架标签列表

  artifact: '{artifact-name}'  # 必填；字符串时引用指定名称的 Artifact 定义，对象时为内联 Artifact

  # depends / suggests / tools 支持 @windows / @unix / @linux / @macos 后缀
  depends: []            # 可选，硬依赖列表（库名或 ext- 前缀的 PHP 扩展名）
  depends@unix: []
  depends@windows: []
  suggests: []           # 可选，可选依赖列表（格式同 depends）
  tools: []              # 可选，仅构建时需要的 ToolPackage 名称
  tools@windows: []

  # ── library / target 专属字段 ────────────────────────────────────────────
  # 以下字段用于构建完成后验证产物是否已正确安装；headers、static-libs、static-bins
  # 支持 @unix / @windows / @linux / @macos 后缀

  # 验证 buildroot/include/ 下是否存在指定头文件或目录
  # 相对路径基于 buildroot/include/，绝对路径直接使用
  headers:
    - openssl             # 对应 buildroot/include/openssl/
    - zlib.h              # 对应 buildroot/include/zlib.h
  headers@unix:
    - ffi.h

  # 验证 buildroot/lib/ 下是否存在指定静态库文件
  # 相对路径基于 buildroot/lib/，绝对路径直接使用
  static-libs@unix:
    - libssl.a
  static-libs@windows:
    - libssl.lib

  # 验证 buildroot/lib/pkgconfig/ 下是否存在指定 .pc 文件
  # 仅在非 Windows 平台检查（pkg-config 在 Windows 上不适用）
  pkg-configs:
    - openssl             # 对应 buildroot/lib/pkgconfig/openssl.pc
    - libssl              # 自动补全 .pc 后缀

  # 验证 buildroot/bin/ 下是否存在指定可执行文件
  # 相对路径基于 buildroot/bin/，绝对路径直接使用
  static-bins:
    - my-tool

  # 包安装完成后注入到全局 PATH 的目录列表，支持路径占位符（见下方说明）
  path:
    - '{pkg_root_path}/rust/bin'

  # 包安装完成后设置的环境变量（覆盖已有值），支持路径占位符
  env:
    MY_VAR: '{build_root_path}/lib'

  # 包安装完成后追加到已有环境变量末尾的值，支持路径占位符
  append-env:
    CFLAGS: ' -I{build_root_path}/include'
```

`path`、`env`、`append-env` 字段的字符串值中支持以下路径占位符：

| 占位符 | 实际路径 |
|---|---|
| `{build_root_path}` | buildroot 目录（`buildroot/`） |
| `{pkg_root_path}` | pkgroot 目录（`pkgroot/`） |
| `{working_dir}` | 工作目录（项目根目录） |
| `{download_path}` | 下载缓存目录（`downloads/`） |
| `{source_path}` | 解压源码目录（`source/`） |
| `{spc_msys2_path}` | MSYS2 根目录（`msys64/`）——仅 Windows |

## tool 包类型

`tool` Package 表示构建其他 Package 时需要的可执行工具，而不是链接进最终 target 的库。Tool Package 可以使用预构建二进制，也可以从源码构建，默认安装在 `pkgroot/` 下的共享 `bin/` 或配置的子目录。其他 Package 通过顶层 `tools` 字段请求这些工具；该依赖集合与 `depends`、`suggests` 分开解析。

```yaml
nasm:
  type: tool
  artifact:
    binary:
      windows-x86_64:
        type: url
        url: 'https://example.com/nasm-win64.zip'
        extract:
          nasm.exe: '{pkg_root_path}/bin/nasm.exe'
          ndisasm.exe: '{pkg_root_path}/bin/ndisasm.exe'
  tool:
    provides: [nasm.exe, ndisasm.exe]
    binary-subdir: bin
    min-version: '2.16'
```

嵌套的 `tool` 对象支持：

| 字段 | 是否必填 | 含义 |
|---|---|---|
| `provides` | 是 | 用于判断工具是否已经安装的可执行文件名 |
| `binary-subdir` | 否 | `install-root` 下存放可执行文件的目录；默认就是安装根目录 |
| `install-root` | 否 | 安装根目录；默认为 `PKG_ROOT_PATH`，支持路径占位符 |
| `min-version` | 否 | 由 `ToolPackage` 暴露的最低版本元数据；installer 当前不会强制校验 |

`tool` 内的字段可以使用 `@windows`、`@unix`、`@linux` 和 `@macos` 后缀。例如，`provides@windows` 可以列出 `.exe` 名称，`provides@unix` 列出 Unix 名称。

## target 包类型

`target` Package 代表一个最终的构建产物，它继承于 `library`，所以包含 `library` 的所有定义字段。其配置文件位于 `config/pkg/target/`。可选的 recipe class 通常放在 `src/Package/Target/`，通过 `#[Target]` 注册，并接收 `TargetPackage`；是否继承 `TargetPackage` 是可选的。

与 `library` 的唯一区别是，`target` 包可以注册成为构建目标，且自动注册构建命令 `spc build:{target-name}`。

## virtual-target 包类型

与 `target` 不同，`virtual-target` 可以省略 `artifact`，即不直接对应一个可构建的实体，而是用于依赖管理和构建调度的抽象构建目标。它与 `target` 使用相同的配置目录、可选 recipe class 目录和 `#[Target]` 注册方式。其余定义基本相同，但 `artifact` 字段可选且通常不设置。`virtual-target` 主要用于以下场景：

- 定义一个抽象的构建目标，供其他包依赖，但不直接对应一个可构建的实体。
- 作为多个 `target` 包的公共依赖，简化依赖关系管理。

典型例子是 `php-cli` 和 `php-fpm` 构建目标。它们没有独立源码，依赖 Artifact 为 `php-src` 的 `php` target；构建调度会决定最终生成 CLI 还是 FPM 二进制。
