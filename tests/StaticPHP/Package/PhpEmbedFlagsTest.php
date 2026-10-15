<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Package;

use Package\Target\php\unix;
use PHPUnit\Framework\TestCase;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

/** @internal */
class PhpEmbedFlagsTest extends TestCase
{
    public function testLibphpFlagsRebuildCoreWithoutLeakingToOtherTargets(): void
    {
        $this->checkEmbedFlags('-ftls-model=initial-exec');
    }

    public function testEmptyLibphpFlagsKeepExistingObjects(): void
    {
        $this->checkEmbedFlags('');
    }

    private function checkEmbedFlags(string $flags): void
    {
        if (SystemTarget::getTargetOS() !== 'Linux') {
            self::markTestSkipped('This fixture uses the Linux libphp target.');
        }
        $directory = trim((string) shell_exec('mktemp -d'));
        self::assertDirectoryExists($directory);
        $original_flags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS_LIBPHP');
        $original_ldflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS');

        try {
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS_LIBPHP=' . $flags);
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS=');
            file_put_contents($directory . '/Makefile', <<<'MAKE'
EXTENSION_DIR = /modules
.PHONY: clean install-sapi install-modules install-build install-headers install-programs
core.lo embed.lo module.lo:
	@printf '%s\n' '$(EXTRA_CFLAGS)' > $@
libphp.la: core.lo embed.lo
	@cat core.lo embed.lo > libphp.flags
	@touch $@
install-sapi: libphp.la
install-modules: module.lo
	@cp module.lo module.flags
install-build install-headers install-programs:
	@:
clean:
	@touch cleaned
	@rm -f core.lo embed.lo module.lo libphp.la

MAKE);
            file_put_contents($directory . '/core.lo', "-O2\n");
            file_put_contents($directory . '/cli.flags', "-O2\n");
            $package = $this->createMock(TargetPackage::class);
            $package->method('getSourceDir')->willReturn($directory);
            $package->method('getLibDir')->willReturn($directory);
            $extension = $this->createMock(PhpExtensionPackage::class);
            $extension->method('isBuildShared')->willReturn(true);
            $extension->method('isBuildWithPhp')->willReturn(true);
            $installer = $this->createMock(PackageInstaller::class);
            $installer->method('getResolvedPackages')->willReturn([$extension]);
            $target = new class {
                use unix;

                private function makeVars(PackageInstaller $installer): array
                {
                    return ['EXTRA_CFLAGS' => '-O2'];
                }
            };
            $target->makeEmbedForUnix($package, $installer, ApplicationContext::get(PackageBuilder::class));

            $expected = trim('-O2 ' . $flags) . "\n";
            self::assertSame($expected . $expected, file_get_contents($directory . '/libphp.flags'));
            self::assertSame("-O2\n", file_get_contents($directory . '/cli.flags'));
            self::assertSame("-O2\n", file_get_contents($directory . '/module.flags'));
            self::assertSame($flags !== '', file_exists($directory . '/cleaned'));
        } finally {
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS_LIBPHP' . ($original_flags === false ? '' : '=' . $original_flags));
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS' . ($original_ldflags === false ? '' : '=' . $original_ldflags));
            FileSystem::removeDir($directory);
        }
    }
}
