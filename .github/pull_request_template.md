## What does this PR do?



## Checklist before merging

> If your PR involves the changes mentioned below and completed the action, please tick the corresponding option.
> If a modification is not involved, please skip it directly.

- If you modified `*.php` or `*.json`, run them locally to ensure your changes are valid:
  - [ ] `composer cs-fix`
  - [ ] `composer analyse`
  - [ ] `composer test`
  - [ ] `bin/spc dev:sort-config`
- If it's an extension or dependency update, please ensure the following:
  - [ ] Add your test combination to `src/globals/test-extensions.php`.
  - [ ] If adding new or fixing bugs, add commit message containing `extension test` or `test extensions` to trigger full test suite.
