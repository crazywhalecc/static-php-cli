<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Package;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;

/**
 * @internal
 */
class PhpExtensionPackageTest extends TestCase
{
    private mixed $savedConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $property = new \ReflectionProperty(PackageConfig::class, 'package_configs');
        $this->savedConfig = $property->getValue();
        $property->setValue(null, [
            'ext-xdebug' => ['type' => 'php-extension', 'php-extension' => ['zend-extension' => true]],
            'ext-pcov' => ['type' => 'php-extension', 'php-extension' => []],
            'ext-simplexml' => ['type' => 'php-extension', 'php-extension' => ['build-with-php' => true]],
            'ext-dom' => ['type' => 'php-extension', 'php-extension' => ['build-with-php' => true]],
            'ext-ctype' => ['type' => 'php-extension', 'php-extension' => []],
        ]);
        ApplicationContext::reset();
        ApplicationContext::initialize();
    }

    protected function tearDown(): void
    {
        new \ReflectionProperty(PackageConfig::class, 'package_configs')->setValue(null, $this->savedConfig);
        ApplicationContext::reset();
        parent::tearDown();
    }

    public function testSharedExtensionLoadStringIncludesSharedBuiltWithPhpExtensions(): void
    {
        // phpize-built shared extensions (one a Zend extension)
        $xdebug = new PhpExtensionPackage('xdebug');
        $xdebug->setBuildShared();
        $pcov = new PhpExtensionPackage('pcov');
        $pcov->setBuildShared();
        // built with php as shared only: php's `make install-modules` puts its .so in the modules dir
        $simplexml = new PhpExtensionPackage('simplexml');
        $simplexml->setBuildShared();
        $simplexml->setBuildWithPhp();
        // built with php, shared and static: configured statically, so no .so to load
        $dom = new PhpExtensionPackage('dom');
        $dom->setBuildShared();
        $dom->setBuildWithPhp();
        $dom->setBuildStatic();
        // static only
        $ctype = new PhpExtensionPackage('ctype');
        $ctype->setBuildStatic();

        $installer = $this->createMock(PackageInstaller::class);
        $installer->method('getResolvedPackages')->willReturn([$xdebug, $pcov, $simplexml, $dom, $ctype]);
        ApplicationContext::set(PackageInstaller::class, $installer);

        static::assertSame(
            sprintf(
                ' -d "extension_dir=%s" -d "zend_extension=xdebug" -d "extension=pcov" -d "extension=simplexml"',
                BUILD_MODULES_PATH
            ),
            $ctype->getSharedExtensionLoadString()
        );
    }

    public function testSharedExtensionLoadStringIsEmptyWithoutSharedExtensions(): void
    {
        $ctype = new PhpExtensionPackage('ctype');
        $ctype->setBuildStatic();

        $installer = $this->createMock(PackageInstaller::class);
        $installer->method('getResolvedPackages')->willReturn([$ctype]);
        ApplicationContext::set(PackageInstaller::class, $installer);

        static::assertSame('', $ctype->getSharedExtensionLoadString());
    }
}
