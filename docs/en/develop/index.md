# Developer Guide

This section covers the StaticPHP development workflow and the foundational knowledge needed to understand how StaticPHP works under the hood.

## Overview

StaticPHP is a binary build tool whose core purpose is managing the build pipeline — downloading and configuring PHP source code, resolving extension dependencies, and invoking the underlying build system (e.g., Docker or a local compiler).

From a development perspective, StaticPHP is an open framework that provides the ability to statically build PHP and other open-source tools together. The project is maintained by [@crazywhalecc](https://github.com/crazywhalecc) and [@henderkes](https://github.com/henderkes), with contributions from the community.

You can think of StaticPHP as a typical PHP CLI project built on [symfony/console](https://symfony.com/doc/current/components/console.html).

## Development Environment

To get started with StaticPHP development, you'll need a PHP development environment with the required dependencies installed.

Requirements:

- PHP 8.4 or later
- Composer
- Git
- PHP extensions: `curl, dom, filter, mbstring, openssl, pcntl, phar, posix, sodium, tokenizer, xml, xmlwriter`

> These PHP extensions are required for StaticPHP's `dev` environment.

### Setup Steps

1. Clone the repository:

    ```bash
    git clone https://github.com/crazywhalecc/static-php-cli.git
    cd static-php-cli
    ```

2. Install PHP dependencies:

    ```bash
    composer install
    ```

3. Verify the setup:

    ```bash
    bin/spc --version
    ```

---

You can continue reading [Project Structure](./structure) to learn more about StaticPHP's framework architecture.
