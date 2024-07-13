# Introduction to project structure

static-php-cli mainly contains three logical components: sources, dependent libraries, and extensions.
These components contains 4 configuration files: `source.json`, `pkg.json`, `lib.json`, and `ext.json`.

A complete process for building standalone static PHP is:

1. Use the source download module `Downloader` to download specified or all source codes. 
    These sources include PHP source code, dependent library source code, and extension source code.
2. Use the source decompression module `SourceExtractor` to decompress the downloaded sources to the compilation directory.
3. Use the dependency tool to calculate the dependent extensions and dependent libraries of the currently added extension, 
    and then compile each library that needs to be compiled in the order of dependencies.
4. After building each dependent library using `Builder` under the corresponding operating system, install it to the `buildroot` directory.
5. If external extensions are included (the source code does not contain extensions within PHP), 
    copy the external extensions to the `source/php-src/ext/` directory.
6. Use `Builder` to build the PHP source code and build target to the `buildroot` directory.

The project is mainly divided into several folders:

- `bin/`: used to store program entry files, including `bin/spc`, `bin/spc-alpine-docker`, `bin/setup-runtime`.
- `config/`: Contains all the extensions and dependent libraries supported by the project, 
    as well as the download link and download methods of these sources. It is divided into files: `lib.json`, `ext.json`, `source.json`, `pkg.json`, `pre-built.json` .
- `src/`: The core code of the project, including the entire framework and commands for compiling various extensions and libraries.
- `vendor/`: The directory that Composer depends on, you do not need to make any modifications to it.

The operating principle is to start a `ConsoleApplication` of `symfony/console`, and then parse the commands entered by the user in the terminal.

## Basic command line structure

`bin/spc` is an entry file, including the Unix common `#!/usr/bin/env php`, 
which is used to allow the system to automatically execute with the PHP interpreter installed on the system.
After the project executes `new ConsoleApplication()`, the framework will automatically register them as commands.

The project does not directly use the Command registration method and command execution method recommended by Symfony. Here are small changes:

1. Each command uses the `#[AsCommand()]` Attribute to register the name and description.
2. Abstract `execute()` so that all commands are based on `BaseCommand` (which is based on `Symfony\Component\Console\Command\Command`), 
    and the execution code of each command itself is written in the `handle()` method .
3. Added variable `$no_motd` to `BaseCommand`, which is used to display the Figlet greeting when the command is executed.
4. `BaseCommand` saves `InputInterface` and `OutputInterface` as member variables. You can use `$this->input` and `$this->output` within the command class.

## Basic source code structure

The source code of the project is located in the `src/SPC` directory, 
supports automatic loading of the PSR-4 standard, and contains the following subdirectories and classes:

- `src/SPC/builder/`: The core compilation command code used to build libraries, 
    PHP and related extensions under different operating systems, and also includes some compilation system tool methods.
- `src/SPC/command/`: All commands of the project are here.
- `src/SPC/doctor/`: Doctor module, which is a relatively independent module used to check the system environment. 
    It can be entered using the command `bin/spc doctor`.
- `src/SPC/exception/`: exception class.
- `src/SPC/store/`: Classes related to storage, files and sources are all here.
- `src/SPC/util/`: Some reusable tool methods are here.
- `src/SPC/ConsoleApplication.php`: command line program entry file.

If you have read the source code, you may find that there is also a `src/globals/` directory, 
which is used to store some global variables, global methods, 
and non-PSR-4 standard PHP source code that is relied upon during the build process, such as extension sanity check code etc.

## Phar application directory issue

Like other php-cli projects, spc itself has additional considerations for paths.
Because spc can run in multiple modes such as `php-cli directly`, `micro SAPI`, `php-cli with Phar`, `vendor with Phar`, etc., 
there are ambiguities in various root directories. A complete explanation is given here.
This problem is generally common in the base class path selection problem of accessing files in PHP projects, especially when used with `micro.sfx`.

Note that this may only be useful for you when developing Phar projects or PHP frameworks.

> Next, we will treat `static-php-cli` (that is, spc) as a normal `php` command line program. You can understand spc as any of your own php-cli applications for reference.

There are three basic constant theoretical values below. We recommend that you introduce these three constants when writing PHP projects:

- `WORKING_DIR`: the working directory when executing PHP scripts

- `SOURCE_ROOT_DIR` or `ROOT_DIR`: the root directory of the project folder, generally the directory where `composer.json` is located

- `FRAMEWORK_ROOT_DIR`: the root directory of the framework used, which may be used by self-developed frameworks. Generally, the framework directory is read-only

You can define these constants in your framework entry or cli applications to facilitate the use of paths in your project.

The following are PHP built-in constant values, which have been defined inside the PHP interpreter:

- `__DIR__`: the directory where the file of the currently executed script is located

- `__FILE__`: the file path of the currently executed script

### Git project mode (source)

Git project mode refers to a framework or program itself stored in plain text in the current folder, and running through `php path/to/entry.php`.

Assume that your project is stored in the `/home/example/static-php-cli/` directory, or your project is the framework itself, 
which contains project files such as `composer.json`:

```
composer.json
src/App/MyCommand.app
vendor/*
bin/entry.php
```

We assume that the above constants are obtained from `src/App/MyCommand.php`:

| Constant             | Value                                                |
|----------------------|------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/static-php-cli`                       |
| `SOURCE_ROOT_DIR`    | `/home/example/static-php-cli`                       |
| `FRAMEWORK_ROOT_DIR` | `/home/example/static-php-cli`                       |
| `__DIR__`            | `/home/example/static-php-cli/src/App`               |
| `__FILE__`           | `/home/example/static-php-cli/src/App/MyCommand.php` |

In this case, the values of `WORKING_DIR`, `SOURCE_ROOT_DIR`, and `FRAMEWORK_ROOT_DIR` are exactly the same: `/home/example/static-php-cli`.

The source code of the framework and the source code of the application are both in the current path.

### Vendor library mode (vendor)

The vendor library mode generally means that your project is a framework or is installed into the project as a composer dependency by other applications, 
and the storage location is in the `vendor/author/XXX` directory.

Suppose your project is `crazywhalecc/static-php-cli`, and you or others install this project in another project using `composer require`.

We assume that static-php-cli contains all files except the `vendor` directory with the same `Git mode`, and get the constant value from `src/App/MyCommand`,
Directory constant should be:

| Constant             | Value                                                                                |
|----------------------|--------------------------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/another-app`                                                          |
| `SOURCE_ROOT_DIR`    | `/home/example/another-app`                                                          |
| `FRAMEWORK_ROOT_DIR` | `/home/example/another-app/vendor/crazywhalecc/static-php-cli`                       |
| `__DIR__`            | `/home/example/another-app/vendor/crazywhalecc/static-php-cli/src/App`               |
| `__FILE__`           | `/home/example/another-app/vendor/crazywhalecc/static-php-cli/src/App/MyCommand.php` |

Here `SOURCE_ROOT_DIR` refers to the root directory of the project using `static-php-cli`.

### Git project Phar mode (source-phar)

Git project Phar mode refers to the mode of packaging the project directory of the Git project mode into a `phar` file. We assume that `/home/example/static-php-cli` will be packaged into a Phar file, and the directory has the following files:

```
composer.json
src/App/MyCommand.app
vendor/*
bin/entry.php
```

When packaged into `app.phar` and stored in the `/home/example/static-php-cli` directory, `app.phar` is executed at this time. Assuming that the `src/App/MyCommand` code is executed, the constant is obtained in the file:

| Constant             | Value                                                                |
|----------------------|----------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/static-php-cli`                                       |
| `SOURCE_ROOT_DIR`    | `phar:///home/example/static-php-cli/app.phar/`                      |
| `FRAMEWORK_ROOT_DIR` | `phar:///home/example/static-php-cli/app.phar/`                      |
| `__DIR__`            | `phar:///home/example/static-php-cli/app.phar/src/App`               |
| `__FILE__`           | `phar:///home/example/static-php-cli/app.phar/src/App/MyCommand.php` |

Because the `phar://` protocol is required to read files in the phar itself, the project root directory and the framework directory will be different from `WORKING_DIR`.

### Vendor Library Phar Mode (vendor-phar)

Vendor Library Phar Mode means that your project is installed as a framework in other projects and stored in the `vendor` directory.

We assume that your project directory structure is as follows:

```
composer.json                           # Composer configuration file of the current project
box.json                                # Configuration file for packaging Phar
another-app.php                         # Entry file of another project
vendor/crazywhalecc/static-php-cli/*    # Your project is used as a dependent library
```

When packaging these files under the directory `/home/example/another-app/` into `app.phar`, the value of the following constant for your project should be:

| Constant             | Value                                                                                                |
|----------------------|------------------------------------------------------------------------------------------------------|
| `WORKING_DIR`        | `/home/example/another-app`                                                                          |
| `SOURCE_ROOT_DIR`    | `phar:///home/example/another-app/app.phar/`                                                         |
| `FRAMEWORK_ROOT_DIR` | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli`                       |
| `__DIR__`            | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli/src/App`               |
| `__FILE__`           | `phar:///home/example/another-app/app.phar/vendor/crazywhalecc/static-php-cli/src/App/MyCommand.php` |
