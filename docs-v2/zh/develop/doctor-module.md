# Doctor 模块

Doctor 模块是一个较为独立的用于检查系统环境的模块，可使用命令 `bin/spc doctor` 进入，入口的命令类在 `DoctorCommand.php` 中。

Doctor 模块是一个检查单，里面有一系列的检查项目和自动修复项目。这些项目都存放在 `src/SPC/doctor/item/` 目录中，
并且使用了两种 Attribute 用作检查项标记和自动修复项目标记：`#[AsCheckItem]` 和 `#[AsFixItem]`。

以现有的检查项 `if necessary tools are installed`，它是用于检查编译必需的包是否安装在 macOS 系统内，下面是它的源码：

```php
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;

#[AsCheckItem('if necessary tools are installed', limit_os: 'Darwin', level: 997)]
public function checkCliTools(): ?CheckResult
{
    $missing = [];
    foreach (self::REQUIRED_COMMANDS as $cmd) {
        if ($this->findCommand($cmd) === null) {
            $missing[] = $cmd;
        }
    }
    if (!empty($missing)) {
        return CheckResult::fail('missing system commands: ' . implode(', ', $missing), 'build-tools', [$missing]);
    }
    return CheckResult::ok();
}
```

属性的第一个参数就是检查项目的名称，后面的 `limit_os` 参数是限制了该检查项仅在指定的系统下触发，`level` 是执行该检查项的优先级，数字越大，优先级越高。

里面用到的 `$this->findCommand()` 方法为 `SPC\builder\traits\UnixSystemUtilTrait` 的方法，用途是查找系统命令所在位置，找不到时返回 NULL。

每个检查项的方法都应该返回一个 `SPC\doctor\CheckResult`：

- 在返回 `CheckResult::fail()` 时，第一个参数用于输出终端的错误提示，第二个参数是在这个检查项可自动修复时的修复项目名称。
- 在返回 `CheckResult::ok()` 时，表明检查通过。你也可以传递一个参数，用于返回检查结果，例如：`CheckResult::ok('OS supported')`。
- 在返回 `CheckResult::fail()` 时，如果包含了第三个参数，第三个参数的数组将被当作 `AsFixItem` 的参数。

下面是这个检查项对应的自动修复项的方法：

```php
#[AsFixItem('build-tools')]
public function fixBuildTools(array $missing): bool
{
    foreach ($missing as $cmd) {
        try {
            shell(true)->exec('brew install ' . escapeshellarg($cmd));
        } catch (RuntimeException) {
            return false;
        }
    }
    return true;
}
```

`#[AsFixItem()]` 属性传入的参数即修复项的名称，该方法必须返回 True 或 False。当返回 False 时，表明自动修复失败，需要手动处理。

此处的代码中 `shell()->exec()` 是项目的执行命令的方法，用于替代 `exec()`、`system()`，同时提供了 debug、获取执行状态、进入目录等特性。
