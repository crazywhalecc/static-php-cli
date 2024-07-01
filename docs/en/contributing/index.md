# Contributing

Thank you for being here, this project welcomes your contributions!

## Contribution Guide

If you have code or documentation to contribute, here's what you need to know first.

1. What type of code are you contributing? (new extensions, bug fixes, security issues, project framework optimizations, documentation)
2. If you contribute new files or new snippets, is your code checked by `php-cs-fixer` and `phpstan`?
3. Have you fully read the [Developer Guide](../develop/) before contributing code?

If you can answer the above questions and have made changes to the code, 
you can initiate a Pull Request in the project GitHub repository in time. 
After the code review is completed, the code can be modified according to the suggestion, or directly merged into the main branch.

## Contribution Type

The main purpose of this project is to compile statically linked PHP binaries, 
and the command line processing function is written based on `symfony/console`. 
Before development, if you are not familiar with it,
Check out the [symfony/console documentation](https://symfony.com/doc/current/components/console.html) first.

### Security Update

Because this project is basically a PHP project running locally, generally speaking, there will be no remote attacks. 
But if you find such a problem, please **DO NOT submit a PR or Issue in the GitHub repository,
You need to contact the project maintainer (crazywhalecc) via [mail](mailto:admin@zhamao.me).

### Fix Bugs

Fixing bugs generally does not involve modification of the project structure and framework, 
so if you can locate the wrong code and fix it directly, please submit a PR directly.

### New Extensions

For adding a new extension, 
you need to understand some basic structure of the project and how to add a new extension according to the existing logic. 
It will be covered in detail in the next section on this page.
In general, you will need:

1. Evaluate whether the extension can be compiled inline into PHP.
2. Evaluate whether the extension's dependent libraries (if any) can be compiled statically.
3. Write library compile commands on different platforms.
4. Verify that the extension and its dependencies are compatible with existing extensions and dependencies.
5. Verify that the extension works normally in `cli`, `micro`, `fpm`, `embed` SAPIs.
6. Write documentation and add your extension.

### Project Framework Optimization

If you are already familiar with the working principle of `symfony/console`, 
and at the same time want to make some modifications or optimizations to the framework of the project, 
please understand the following things first:

1. Adding extensions does not belong to project framework optimization, 
but if you find that you have to optimize the framework when adding new extensions, 
you need to modify the framework itself before adding extensions.
2. For some large-scale logical modifications (such as those involving LibraryBase, Extension objects, etc.), 
it is recommended to submit an Issue or Draft PR for discussion first.
3. In the early stage of the project, it was a pure private development project, and there were some Chinese comments in the code. 
After internationalizing your project you can submit a PR to translate these comments into English.
4. Please do not submit more useless code fragments in the code, 
such as a large number of unused variables, methods, classes, and code that has been rewritten many times.
