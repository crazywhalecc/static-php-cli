# StaticPHP Build Class Patterns

## Contents

- When a class is needed
- Package attributes
- Stage and hook behavior
- Executor choices
- Common patterns
- Failure-safe edits

## When a Class Is Needed

Do not add a PHP package class for simple metadata. Use YAML alone when StaticPHP can infer the configure args, dependencies, and verification.

Add or update a class when the package needs:

- OS-specific build commands.
- Custom validation before building.
- Custom PHP configure arguments.
- Patches or file edits before build.
- Hooks into another package's stage.
- Custom source/binary download or extraction logic.
- Package info shown by dev tooling.

Package classes live in `src/Package/*` and are discovered through PSR-4 plus attributes. Config must exist first; `PackageLoader` throws if an attribute references an undefined package.

## Package Attributes

Class-level package attributes:

- `#[Extension('curl')]` maps to package `ext-curl` if the prefix is omitted.
- `#[Library('openssl')]`
- `#[Target('php')]`
- `#[Tool('zig')]`

Method-level attributes:

- `#[BuildFor('Linux'|'Darwin'|'Windows')]`: registers the `build` stage for that OS.
- `#[Stage('name')]`: registers a named stage; defaults to the method name when omitted.
- `#[BeforeStage('package', 'stage', 'only-when-package-resolved')]`: hook before a target package stage.
- `#[AfterStage('package', 'stage', 'only-when-package-resolved')]`: hook after a target package stage.
- `#[PatchBeforeBuild]`: runs once before package build unless `.spc-patched` exists; return `true` to write that marker.
- `#[CustomPhpConfigureArg('Linux')]`: supplies custom extension configure args.
- `#[Validate]`: validates environment/source assumptions.
- `#[Info]`: returns package information for tooling.
- `#[InitPackage]`: runs while loading a package class.
- `#[ResolveBuild]`: target package callback for resolving build dependencies.
- `#[ConditionalOn(SomeClass::class)]`: conditionally enables before/after hooks only when DI has the class.

## Stage and Hook Behavior

`PackageBuilder::buildPackage()` does:

1. Ensure build directories exist.
2. Skip if already installed unless forced.
3. Ensure source exists for non-virtual packages.
4. Emit `PatchBeforeBuild` callbacks.
5. Run the `build` stage.
6. Install license data.
7. Record tool package versions where relevant.

`Package::runStage()` wraps a stage with:

1. `BeforeStage` callbacks.
2. The stage method itself.
3. `AfterStage` callbacks.

SPC exceptions bind package and stage metadata. Preserve that behavior by throwing existing `SPCException` subclasses instead of raw exceptions when adding new failure paths.

## Executor Choices

Use existing executors where possible:

- `UnixAutoconfExecutor`: for Unix packages using `./configure && make && make install`. It injects default static flags and copies `config.log` into SPC logs on failure.
- `UnixCMakeExecutor`: for Unix CMake packages. It writes toolchain settings and copies CMake configure/error/output logs on failure.
- `WindowsCMakeExecutor`: for Windows CMake packages.
- `shell()->cd(...)->initializeEnv($pkg)`: for custom Unix command chains.
- `cmd()->cd(...)`: for Windows command chains.

Prefer `FileSystem` helpers for file edits and copies so behavior stays consistent.

## Common Patterns

Minimal library class:

```php
#[Library('example')]
class example
{
    #[BuildFor('Linux')]
    public function build(LibraryPackage $pkg): void
    {
        (new UnixAutoconfExecutor($pkg))
            ->configure()
            ->make();
    }
}
```

Extension hook into PHP build:

```php
#[Extension('curl')]
class curl
{
    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-curl')]
    public function patchBeforePhpBuild(): void
    {
        // Adjust env, generated build files, or linker flags.
    }
}
```

Validation:

```php
#[Validate]
public function validate(): void
{
    if (SystemTarget::getTargetOS() === 'Windows' && WindowsUtil::findCommand('perl.exe') === null) {
        throw new EnvironmentException('You need to install perl first!');
    }
}
```

Custom configure args should be used when YAML `arg-type` is `custom` or when args need package/install context:

```php
#[CustomPhpConfigureArg('Linux')]
public function configureArg(PhpExtensionPackage $ext): string
{
    return '--with-example=' . $ext->getBuildRootPath();
}
```

## Failure-Safe Edits

- Make hooks conditional with the third `BeforeStage`/`AfterStage` argument when a dependency must be resolved before the hook should run.
- Keep platform-specific code under matching `#[BuildFor]` methods rather than branching heavily inside one method.
- If a patch changes upstream source, place patch files in `src/globals/patch/` and describe them with `#[PatchDescription]` where appropriate.
- If a class only mutates environment variables for another package, search for existing hooks first to avoid duplicate linker flags.
- After adding attributes, run registry/config tests because invalid stages and unknown packages are caught during loading.
