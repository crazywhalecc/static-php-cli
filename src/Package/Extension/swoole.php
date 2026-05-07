<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SPCConfigUtil;

#[Extension('swoole')]
class swoole extends PhpExtensionPackage
{
    #[Validate]
    public function validate(PackageInstaller $installer): void
    {
        // swoole-hook-odbc conflicts with pdo_odbc
        if ($installer->getPhpExtensionPackage('swoole-hook-odbc') && $installer->getPhpExtensionPackage('pdo_odbc')?->isBuildStatic()) {
            throw new WrongUsageException('swoole-hook-odbc provides pdo_odbc, if you enable odbc hook for swoole, you must remove pdo_odbc extension.');
        }
        // swoole-hook-pgsql conflicts with pdo_pgsql
        if ($installer->getPhpExtensionPackage('swoole-hook-pgsql') && $installer->getPhpExtensionPackage('pdo_pgsql')?->isBuildStatic()) {
            throw new WrongUsageException('swoole-hook-pgsql provides pdo_pgsql, if you enable pgsql hook for swoole, you must remove pdo_pgsql extension.');
        }
        // swoole-hook-sqlite conflicts with pdo_sqlite
        if ($installer->getPhpExtensionPackage('swoole-hook-sqlite') && $installer->getPhpExtensionPackage('pdo_sqlite')?->isBuildStatic()) {
            throw new WrongUsageException('swoole-hook-sqlite provides pdo_sqlite, if you enable sqlite hook for swoole, you must remove pdo_sqlite extension.');
        }
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-swoole')]
    #[PatchDescription('Fix maximum version check for Swoole 6.2')]
    public function patchBeforeMake(): void
    {
        FileSystem::replaceFileStr($this->getSourceDir() . '/ext-src/php_swoole_private.h', 'PHP_VERSION_ID > 80500', 'PHP_VERSION_ID >= 80600');
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-swoole')]
    #[PatchDescription('Fix swoole with event extension <util.h> conflict bug on macOS')]
    public function patchBeforeMake2(): void
    {
        if (SystemTarget::getTargetOS() === 'Darwin') {
            // Fix swoole with event extension <util.h> conflict bug
            $util_path = shell()->execWithResult('xcrun --show-sdk-path', false)[1][0] . '/usr/include/util.h';
            FileSystem::replaceFileStr(
                "{$this->getSourceDir()}/thirdparty/php/standard/proc_open.cc",
                'include <util.h>',
                "include \"{$util_path}\"",
            );
        }
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder, PackageInstaller $installer): string
    {
        // enable swoole
        $arg = '--enable-swoole' . ($shared ? '=shared' : '');

        // commonly used feature: coroutine-time
        $arg .= ' --enable-swoole-coro-time --with-pic';

        $arg .= $builder->getOption('enable-zts') ? ' --enable-swoole-thread --disable-thread-context' : ' --disable-swoole-thread --enable-thread-context';

        // required features: curl, openssl (but curl hook is buggy for php 8.0)
        $arg .= php::getPHPVersionID() >= 80100 ? ' --enable-swoole-curl' : ' --disable-swoole-curl';
        $arg .= ' --enable-openssl';

        // additional features that only require libraries
        $arg .= $installer->getLibraryPackage('libcares') ? ' --enable-cares' : '';
        $arg .= $installer->getLibraryPackage('brotli') ? (' --enable-brotli --with-brotli-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $installer->getLibraryPackage('nghttp2') ? (' --with-nghttp2-dir=' . BUILD_ROOT_PATH) : '';
        $arg .= $installer->getLibraryPackage('zstd') ? ' --enable-zstd' : '';
        $arg .= $installer->getLibraryPackage('liburing') ? ' --enable-iouring' : '';
        $arg .= $installer->getPhpExtensionPackage('sockets') ? ' --enable-sockets' : '';

        // enable additional features that require the pdo extension, but conflict with pdo_* extensions
        // to make sure everything works as it should, this is done in fake addon extensions
        $arg .= $installer->getPhpExtensionPackage('swoole-hook-pgsql') ? ' --enable-swoole-pgsql' : ' --disable-swoole-pgsql';
        $arg .= $installer->getPhpExtensionPackage('swoole-hook-mysql') ? ' --enable-mysqlnd' : ' --disable-mysqlnd';
        $arg .= $installer->getPhpExtensionPackage('swoole-hook-sqlite') ? ' --enable-swoole-sqlite' : ' --disable-swoole-sqlite';
        if ($installer->getPhpExtensionPackage('swoole-hook-odbc')) {
            $config = new SPCConfigUtil()->getLibraryConfig($installer->getLibraryPackage('unixodbc'));
            $arg .= " --with-swoole-odbc=unixODBC,{$builder->getBuildRootPath()} SWOOLE_ODBC_LIBS=\"{$config['libs']}\"";
        }

        // Get version from source directory
        $ver = null;
        $file = SOURCE_PATH . '/php-src/ext/swoole/include/swoole_version.h';
        // Match #define SWOOLE_VERSION "5.1.3"
        $pattern = '/#define SWOOLE_VERSION "(.+)"/';
        if (preg_match($pattern, file_get_contents($file), $matches)) {
            $ver = $matches[1];
        }

        if ($ver && $ver >= '6.1.0') {
            $arg .= ' --enable-swoole-stdext';
        }

        if (SystemTarget::getTargetOS() === 'Darwin') {
            $arg .= ' ac_cv_lib_pthread_pthread_barrier_init=no';
        }

        return $arg;
    }

    #[AfterStage('php', [php::class, 'smokeTestCliForUnix'], 'ext-swoole-hook-mysql')]
    public function mysqlTest(PackageInstaller $installer): void
    {
        [$ret, $out] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n' . $this->getSharedExtensionLoadString() . ' --ri "swoole"', false);
        $out = implode('', $out);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: php-cli returned {$ret}", validation_module: 'extension swoole_hook_mysql sanity check');
        }
        // mysqlnd
        if ($installer->getPhpExtensionPackage('swoole-hook-mysql') && !str_contains($out, 'mysqlnd')) {
            throw new ValidationException('swoole mysql hook is not enabled correctly.', validation_module: 'Extension swoole mysql hook availability check');
        }
        // coroutine_odbc
        if ($installer->getPhpExtensionPackage('swoole-hook-odbc') && !str_contains($out, 'coroutine_odbc')) {
            throw new ValidationException('swoole odbc hook is not enabled correctly.', validation_module: 'Extension swoole odbc hook availability check');
        }
        // coroutine_pgsql
        if ($installer->getPhpExtensionPackage('swoole-hook-pgsql') && !str_contains($out, 'coroutine_pgsql')) {
            throw new ValidationException(
                'swoole pgsql hook is not enabled correctly.',
                validation_module: 'Extension swoole pgsql hook availability check'
            );
        }
        // coroutine_sqlite
        if ($installer->getPhpExtensionPackage('swoole-hook-sqlite') && !str_contains($out, 'coroutine_sqlite')) {
            throw new ValidationException(
                'swoole sqlite hook is not enabled correctly.',
                validation_module: 'Extension swoole sqlite hook availability check'
            );
        }
    }
}
