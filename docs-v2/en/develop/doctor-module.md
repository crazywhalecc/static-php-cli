# Doctor module

The Doctor module is a relatively independent module used to check the system environment, which can be entered with the command `bin/spc doctor`, and the entry command class is in `DoctorCommand.php`.

The Doctor module is a checklist with a series of check items and automatic repair items.
These items are stored in the `src/SPC/doctor/item/` directory,
And two Attributes are used as check item tags and auto-fix item tags: `#[AsCheckItem]` and `#[AsFixItem]`.

Take the existing check item `if necessary tools are installed`,
which is used to check whether the packages necessary for compilation are installed in the macOS system.
The following is its source code:

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

The first parameter of the attribute is the name of the check item,
and the following `limit_os` parameter restricts the check item to be triggered only under the specified system,
and `level` is the priority of executing the check item, the larger the number, the higher the priority higher.

The `$this->findCommand()` method used in it is the method of `SPC\builder\traits\UnixSystemUtilTrait`,
the purpose is to find the location of the system command, and return NULL if it cannot be found.

Each check item method should return a `SPC\doctor\CheckResult`:

- When returning `CheckResult::fail()`, the first parameter is used to output the error prompt of the terminal,
  and the second parameter is the name of the repair item when this check item can be automatically repaired.
- When `CheckResult::ok()` is returned, the check passed. You can also pass a parameter to return the check result, for example: `CheckResult::ok('OS supported')`.
- When returning `CheckResult::fail()`, if the third parameter is included, the array of the third parameter will be used as the parameter of `AsFixItem`.

The following is the method for automatically repairing items corresponding to this check item:

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

`#[AsFixItem()]` first parameter is the name of the fix item, and this method must return True or False.
When False is returned, the automatic repair failed and manual handling is required.

In the code here, `shell()->exec()` is the method of executing commands of the project,
which is used to replace `exec()` and `system()`, and also provides debugging, obtaining execution status,
entering directories, etc. characteristic.
