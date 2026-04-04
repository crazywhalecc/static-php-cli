<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\store\SourcePatcher;

/**
 * @internal
 */
class SourcePatcherTest extends TestCase
{
    private string $defDir;

    private string $defFile;

    private string $libDir;

    private string $libFile;

    protected function setUp(): void
    {
        // Create fake php-src/ext/libxml directory under SOURCE_PATH
        $this->defDir = SOURCE_PATH . '/php-src/ext/libxml';
        if (!is_dir($this->defDir)) {
            mkdir($this->defDir, 0755, true);
        }
        $this->defFile = $this->defDir . '/php_libxml2.def';

        // Create fake buildroot/lib directory
        $this->libDir = BUILD_ROOT_PATH . '/lib';
        if (!is_dir($this->libDir)) {
            mkdir($this->libDir, 0755, true);
        }
        $this->libFile = $this->libDir . '/libxml2_a.lib';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->defFile)) {
            unlink($this->defFile);
        }
        if (file_exists($this->libFile)) {
            unlink($this->libFile);
        }
        @rmdir(SOURCE_PATH . '/php-src/ext/libxml');
        @rmdir(SOURCE_PATH . '/php-src/ext');
        @rmdir(SOURCE_PATH . '/php-src');
    }

    public function testPatchLibxml2DefNoOpWhenDefFileMissing(): void
    {
        // No .def file — should return silently
        SourcePatcher::patchLibxml2DefForWindows();
        $this->assertFileDoesNotExist($this->defFile);
    }

    public function testPatchLibxml2DefNoOpWhenLibFileMissing(): void
    {
        // .def exists but no .lib — should return silently
        file_put_contents($this->defFile, "EXPORTS\nxmlUCSIsArabic\n");
        $original = file_get_contents($this->defFile);

        SourcePatcher::patchLibxml2DefForWindows();

        $this->assertEquals($original, file_get_contents($this->defFile));
    }
}
