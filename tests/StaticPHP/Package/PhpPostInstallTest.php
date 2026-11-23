<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Package;

use Package\Target\php;
use PHPUnit\Framework\TestCase;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;

/**
 * @internal
 */
class PhpPostInstallTest extends TestCase
{
    /**
     * Cross builds emit target-architecture binaries, so every post-install smoke test
     * (cli/cgi/micro/embed/frankenphp) would exit with an exec-format error on the build
     * host and fail the build. postInstall() must return before scheduling any of them.
     */
    public function testPostInstallSkipsSmokeTestsWhenCrossCompiling(): void
    {
        putenv('SPC_TARGET=' . (GNU_ARCH === 'aarch64' ? 'x86_64-linux-musl' : 'aarch64-linux-musl'));
        try {
            $package = $this->createMock(TargetPackage::class);
            $package->method('getName')->willReturn('php');
            $package->expects($this->never())->method('runStage');

            // The guard must return before the installer is touched at all.
            $installer = $this->createMock(PackageInstaller::class);

            (new php('php', 'target'))->postInstall($package, $installer);
        } finally {
            putenv('SPC_TARGET');
        }
    }
}
