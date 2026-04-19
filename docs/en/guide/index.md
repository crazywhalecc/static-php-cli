# Guide

## What is StaticPHP?

StaticPHP is a build tool that compiles the PHP interpreter together with any extensions you need into a single self-contained binary. The target system doesn't need PHP or any runtime libraries installed — just copy the binary and run it. Builds target Linux, macOS, and Windows.

## Why bother with a static PHP binary?

A typical PHP installation is tightly coupled to the system: you install PHP, then extensions, then spend time dealing with version mismatches across distros. A static binary sidesteps all of that — what you get is a single executable that runs on any machine of the same architecture, no setup required.

Common use cases:

- **Distributing CLI tools** — Ship tools like Composer, PHPStan, or your own CLI as a single file. Users don't need PHP installed.
- **Leaner containers** — Replace a bloated `php:8.x` base image with a minimal image (or even `FROM scratch`) carrying just a static binary.
- **Server applications** — Build a static binary with FPM or FrankenPHP baked in. Deployment becomes a file copy, with no dependency on the host environment.

## phpmicro: ship PHP and your code as one file

[phpmicro](https://github.com/easysoft/phpmicro) is a third-party PHP SAPI that StaticPHP supports out of the box. It merges the PHP interpreter with your `.php` source or `.phar` archive into a single self-extracting executable (`.sfx`).

```
micro.sfx + your-app.phar = your-app   # one file, zero dependencies
```

This is ideal for distributing PHP-based CLI tools: the end user just gets an ordinary executable with no idea PHP is involved.

## Improving how you ship and deploy PHP projects

**Drop the heavy Docker base image**

The official `php:8.x` image can be hundreds of megabytes, most of which is just the PHP runtime. Swap it for a static PHP binary with a minimal base image — or `FROM scratch` — and you can get container sizes down to single-digit megabytes with noticeably faster startup times.

**Ship PHP CLI tools like native binaries**

Build your CLI with [symfony/console](https://symfony.com/doc/current/components/console.html) or [Laravel Zero](https://laravel-zero.com), bundle it into a `.phar` with [Box](https://github.com/box-project/box), then merge it with phpmicro. The result is a single distributable executable — the same experience users expect from Go or Rust tools, with no PHP runtime required on their end.

**Single-file web apps with FrankenPHP**

[FrankenPHP](https://frankenphp.dev) is a modern PHP app server with built-in HTTP/2, HTTP/3, and automatic HTTPS. StaticPHP can compile FrankenPHP together with your chosen extensions into one binary. The result is a complete web server in a single file — no Nginx, no PHP-FPM, just deploy and run.

## Next steps

- [Installation](./installation) — Get the StaticPHP build tool
- [First Build](./first-build) — Full walkthrough: from downloading sources to a working executable
- [CLI Reference](./cli-reference) — Every command and option, in one place
