<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Package;

use Package\Extension\zip;
use PHPUnit\Framework\TestCase;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageBuilder;

/** @internal */
class ZipTest extends TestCase
{
    public function testSourceSelectionUsesRequestedPhpVersion(): void
    {
        $zip = new zip('zip');
        $originalBuilder = ApplicationContext::get(PackageBuilder::class);

        try {
            foreach ([
                '8.4' => 'ext-zip',
                '8.5' => null,
                '8.6.0RC2' => null,
            ] as $version => $artifact) {
                new PackageBuilder(['dl-with-php' => $version]);
                self::assertSame($artifact, $zip->getArtifact()?->getName(), $version);
            }
        } finally {
            ApplicationContext::set(PackageBuilder::class, $originalBuilder);
        }
    }
}
