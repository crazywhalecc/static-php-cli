<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\DownloaderOptions;

/**
 * @internal
 */
class DownloaderOptionsTest extends TestCase
{
    public function testAddToListStartsAListWhenTheOptionWasNotGiven(): void
    {
        $this->assertSame('zig', DownloaderOptions::addToList(false, ['zig']));
        $this->assertSame('zig', DownloaderOptions::addToList('', ['zig']));
    }

    public function testAddToListKeepsAGlobalFlag(): void
    {
        // The doctor's pkg-config fix passes binary-only => true; adding zig must not narrow it.
        $this->assertTrue(DownloaderOptions::addToList(true, ['zig']));
        // The flag given on the command line without a value.
        $this->assertNull(DownloaderOptions::addToList(null, ['zig']));
    }

    public function testAddToListMergesWithoutDuplicates(): void
    {
        $this->assertSame('upx,zig,llvm-tools', DownloaderOptions::addToList('upx,zig', ['zig', 'llvm-tools']));
    }

    public function testAddToListIgnoresKeysOfTheNames(): void
    {
        $this->assertSame('zig', DownloaderOptions::addToList(false, ['zig' => 'zig']));
    }
}
