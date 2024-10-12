<?php

declare(strict_types=1);

namespace SPC\Tests\builder;

use PHPUnit\Framework\TestCase;
use SPC\builder\BuilderProvider;
use SPC\builder\Extension;
use SPC\util\CustomExt;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
class ExtensionTest extends TestCase
{
    private Extension $extension;

    protected function setUp(): void
    {
        $builder = BuilderProvider::makeBuilderByInput(new ArgvInput());
        [$extensions, $libs] = DependencyUtil::getExtsAndLibs(['mbregex']);
        $builder->proveLibs($libs);
        CustomExt::loadCustomExt();
        foreach ($extensions as $extension) {
            $class = CustomExt::getExtClass($extension);
            $ext = new $class($extension, $builder);
            $builder->addExt($ext);
        }
        foreach ($builder->getExts() as $ext) {
            $ext->checkDependency();
        }
        $this->extension = $builder->getExt('mbregex');
    }

    public function testPatches()
    {
        $this->assertFalse($this->extension->patchBeforeBuildconf());
        $this->assertFalse($this->extension->patchBeforeConfigure());
        $this->assertFalse($this->extension->patchBeforeMake());
    }

    public function testGetExtensionDependency()
    {
        $this->assertEquals('mbstring', current($this->extension->getExtensionDependency())->getName());
    }

    public function testGetWindowsConfigureArg()
    {
        $this->assertEquals('', $this->extension->getWindowsConfigureArg());
    }

    public function testGetConfigureArg()
    {
        $this->assertEquals('', $this->extension->getUnixConfigureArg());
    }

    public function testGetExtVersion()
    {
        // only swoole has version, we cannot test it
        $this->assertEquals(null, $this->extension->getExtVersion());
    }

    public function testGetDistName()
    {
        $this->assertEquals('mbregex', $this->extension->getName());
    }

    public function testRunCliCheckWindows()
    {
        if (is_unix()) {
            $this->markTestIncomplete('This test is for Windows only');
        } else {
            $this->extension->runCliCheckWindows();
            $this->assertTrue(true);
        }
    }

    public function testGetLibFilesString()
    {
        $this->assertStringEndsWith('libonig.a', $this->extension->getLibFilesString());
    }

    public function testGetName()
    {
        $this->assertEquals('mbregex', $this->extension->getName());
    }

    public function testGetUnixConfigureArg()
    {
        $this->assertEquals('', $this->extension->getUnixConfigureArg());
    }

    public function testGetEnableArg()
    {
        $this->assertEquals('', $this->extension->getEnableArg());
    }
}
