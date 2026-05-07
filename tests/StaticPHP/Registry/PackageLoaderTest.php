<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Registry;

use PHPUnit\Framework\TestCase;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\RegistryException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Registry\PackageLoader;

/**
 * @internal
 */
class PackageLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/package_loader_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset PackageLoader state
        $reflection = new \ReflectionClass(PackageLoader::class);

        $property = $reflection->getProperty('packages');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('after_stages');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('loaded_classes');
        $property->setAccessible(true);
        $property->setValue(null, []);

        // Reset PackageConfig state
        $configReflection = new \ReflectionClass(PackageConfig::class);
        $configProperty = $configReflection->getProperty('package_configs');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null, []);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Reset PackageLoader state
        $reflection = new \ReflectionClass(PackageLoader::class);

        $property = $reflection->getProperty('packages');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('after_stages');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('loaded_classes');
        $property->setAccessible(true);
        $property->setValue(null, []);

        // Reset PackageConfig state
        $configReflection = new \ReflectionClass(PackageConfig::class);
        $configProperty = $configReflection->getProperty('package_configs');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null, []);
    }

    public function testInitPackageInstancesOnlyRunsOnce(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');

        PackageLoader::initPackageInstances();
        PackageLoader::initPackageInstances();

        // Should only initialize once
        $this->assertTrue(PackageLoader::hasPackage('test-lib'));
    }

    public function testInitPackageInstancesCreatesLibraryPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');

        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-lib');
        $this->assertInstanceOf(LibraryPackage::class, $package);
    }

    public function testInitPackageInstancesCreatesPhpExtensionPackage(): void
    {
        $this->createTestPackageConfig('test-ext', 'php-extension');

        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-ext');
        $this->assertInstanceOf(PhpExtensionPackage::class, $package);
    }

    public function testInitPackageInstancesCreatesTargetPackage(): void
    {
        $this->createTestPackageConfig('test-target', 'target');

        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-target');
        $this->assertInstanceOf(TargetPackage::class, $package);
    }

    public function testInitPackageInstancesCreatesVirtualTargetPackage(): void
    {
        $this->createTestPackageConfig('test-virtual-target', 'virtual-target');

        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-virtual-target');
        $this->assertInstanceOf(TargetPackage::class, $package);
    }

    public function testInitPackageInstancesThrowsExceptionForUnknownType(): void
    {
        $this->createTestPackageConfig('test-unknown', 'unknown-type');

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('has unknown type');

        PackageLoader::initPackageInstances();
    }

    public function testHasPackageReturnsTrueForExistingPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $this->assertTrue(PackageLoader::hasPackage('test-lib'));
    }

    public function testHasPackageReturnsFalseForNonExistingPackage(): void
    {
        PackageLoader::initPackageInstances();

        $this->assertFalse(PackageLoader::hasPackage('non-existent'));
    }

    public function testGetPackageReturnsPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-lib');
        $this->assertInstanceOf(LibraryPackage::class, $package);
    }

    public function testGetPackageThrowsExceptionForNonExistingPackage(): void
    {
        PackageLoader::initPackageInstances();

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('not found');

        PackageLoader::getPackage('non-existent');
    }

    public function testGetTargetPackageReturnsTargetPackage(): void
    {
        $this->createTestPackageConfig('test-target', 'target');
        PackageLoader::initPackageInstances();

        $package = PackageLoader::getTargetPackage('test-target');
        $this->assertInstanceOf(TargetPackage::class, $package);
    }

    public function testGetTargetPackageThrowsExceptionForNonTargetPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('is not a TargetPackage');

        PackageLoader::getTargetPackage('test-lib');
    }

    public function testGetLibraryPackageReturnsLibraryPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $package = PackageLoader::getLibraryPackage('test-lib');
        $this->assertInstanceOf(LibraryPackage::class, $package);
    }

    public function testGetLibraryPackageThrowsExceptionForNonLibraryPackage(): void
    {
        $this->createTestPackageConfig('ext-test-ext', 'php-extension');
        PackageLoader::initPackageInstances();

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('is not a LibraryPackage');

        PackageLoader::getLibraryPackage('ext-test-ext');
    }

    public function testGetPackagesReturnsAllPackages(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        $this->createTestPackageConfig('test-ext', 'php-extension');
        $this->createTestPackageConfig('test-target', 'target');
        PackageLoader::initPackageInstances();

        $packages = iterator_to_array(PackageLoader::getPackages());
        $this->assertCount(3, $packages);
    }

    public function testGetPackagesWithTypeFilterReturnsFilteredPackages(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        $this->createTestPackageConfig('test-ext', 'php-extension');
        $this->createTestPackageConfig('test-target', 'target');
        PackageLoader::initPackageInstances();

        $packages = iterator_to_array(PackageLoader::getPackages('library'));
        $this->assertCount(1, $packages);
        $this->assertArrayHasKey('test-lib', $packages);
    }

    public function testGetPackagesWithArrayTypeFilterReturnsFilteredPackages(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        $this->createTestPackageConfig('test-ext', 'php-extension');
        $this->createTestPackageConfig('test-target', 'target');
        PackageLoader::initPackageInstances();

        $packages = iterator_to_array(PackageLoader::getPackages(['library', 'target']));
        $this->assertCount(2, $packages);
        $this->assertArrayHasKey('test-lib', $packages);
        $this->assertArrayHasKey('test-target', $packages);
    }

    public function testLoadFromClassWithLibraryAttribute(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $class = new #[Library('test-lib')] class {};

        PackageLoader::loadFromClass(get_class($class));

        $this->assertTrue(PackageLoader::hasPackage('test-lib'));
    }

    public function testLoadFromClassWithExtensionAttribute(): void
    {
        $this->createTestPackageConfig('ext-test-ext', 'php-extension');
        PackageLoader::initPackageInstances();

        $class = new #[Extension('ext-test-ext')] class {};

        PackageLoader::loadFromClass(get_class($class));

        $this->assertTrue(PackageLoader::hasPackage('ext-test-ext'));
    }

    public function testLoadFromClassWithTargetAttribute(): void
    {
        $this->createTestPackageConfig('test-target', 'target');
        PackageLoader::initPackageInstances();

        $class = new #[Target('test-target')] class {};

        PackageLoader::loadFromClass(get_class($class));

        $this->assertTrue(PackageLoader::hasPackage('test-target'));
    }

    public function testLoadFromClassThrowsExceptionForUndefinedPackage(): void
    {
        PackageLoader::initPackageInstances();

        $class = new #[Library('undefined-lib')] class {};

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('not defined in config');

        PackageLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassThrowsExceptionForTypeMismatch(): void
    {
        $this->createTestPackageConfig('ext-test-lib', 'library');
        PackageLoader::initPackageInstances();

        // Try to load with Extension attribute but config says library
        $class = new #[Extension('ext-test-lib')] class {};

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('type mismatch');

        PackageLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassSkipsDuplicateClasses(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $className = get_class(new #[Library('test-lib')] class {});

        // Load twice
        PackageLoader::loadFromClass($className);
        PackageLoader::loadFromClass($className);

        // Should not throw exception
        $this->assertTrue(PackageLoader::hasPackage('test-lib'));
    }

    public function testLoadFromClassWithNoPackageAttribute(): void
    {
        PackageLoader::initPackageInstances();

        $class = new class {
            public function regularMethod(): void {}
        };

        // Should not throw exception
        PackageLoader::loadFromClass(get_class($class));

        // Verify no side effects
        $this->assertTrue(true);
    }

    public function testCheckLoadedStageEventsThrowsExceptionForUnknownPackage(): void
    {
        PackageLoader::initPackageInstances();

        // Manually add a before_stage for non-existent package
        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'non-existent-package' => [
                'stage-name' => [[fn () => null, null]],
            ],
        ]);

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('unknown package');

        PackageLoader::checkLoadedStageEvents();
    }

    public function testCheckLoadedStageEventsThrowsExceptionForUnknownStage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        // Add a build function for current OS so the stage validation is triggered
        $package = PackageLoader::getPackage('test-lib');
        $package->addBuildFunction(PHP_OS_FAMILY, fn () => null);

        // Manually add a before_stage for non-existent stage
        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'test-lib' => [
                'non-existent-stage' => [[fn () => null, null]],
            ],
        ]);

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('is not registered');

        PackageLoader::checkLoadedStageEvents();
    }

    public function testCheckLoadedStageEventsThrowsExceptionForUnknownOnlyWhenPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        $package = PackageLoader::getPackage('test-lib');
        $package->addStage('test-stage', fn () => null);

        // Manually add a before_stage with unknown only_when_package_resolved
        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'test-lib' => [
                'test-stage' => [[fn () => null, 'non-existent-package']],
            ],
        ]);

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('unknown only_when_package_resolved package');

        PackageLoader::checkLoadedStageEvents();
    }

    public function testCheckLoadedStageEventsDoesNotThrowForNonCurrentOSPackage(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');
        PackageLoader::initPackageInstances();

        // Add a build function for a different OS (not current OS)
        $package = PackageLoader::getPackage('test-lib');
        $otherOS = PHP_OS_FAMILY === 'Windows' ? 'Linux' : 'Windows';
        $package->addBuildFunction($otherOS, fn () => null);

        // Manually add a before_stage for 'build' stage
        // This should NOT throw an exception because the package has no build function for current OS
        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'test-lib' => [
                'build' => [[fn () => null, null]],
            ],
        ]);

        // This should not throw an exception
        PackageLoader::checkLoadedStageEvents();

        $this->assertTrue(true); // If we get here, the test passed
    }

    public function testGetBeforeStageCallbacksReturnsCallbacks(): void
    {
        PackageLoader::initPackageInstances();

        // Manually add some before_stage callbacks
        $callback1 = fn () => 'callback1';
        $callback2 = fn () => 'callback2';

        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('before_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'test-package' => [
                'test-stage' => [
                    [$callback1, null],
                    [$callback2, null],
                ],
            ],
        ]);

        $callbacks = iterator_to_array(PackageLoader::getBeforeStageCallbacks('test-package', 'test-stage'));
        $this->assertCount(2, $callbacks);
    }

    public function testGetAfterStageCallbacksReturnsCallbacks(): void
    {
        PackageLoader::initPackageInstances();

        // Manually add some after_stage callbacks
        $callback1 = fn () => 'callback1';
        $callback2 = fn () => 'callback2';

        $reflection = new \ReflectionClass(PackageLoader::class);
        $property = $reflection->getProperty('after_stages');
        $property->setAccessible(true);
        $property->setValue(null, [
            'test-package' => [
                'test-stage' => [
                    [$callback1, null],
                    [$callback2, null],
                ],
            ],
        ]);

        $callbacks = PackageLoader::getAfterStageCallbacks('test-package', 'test-stage');
        $this->assertCount(2, $callbacks);
    }

    public function testGetBeforeStageCallbacksReturnsEmptyForNonExistentPackage(): void
    {
        PackageLoader::initPackageInstances();

        $callbacks = iterator_to_array(PackageLoader::getBeforeStageCallbacks('non-existent', 'stage'));
        $this->assertEmpty($callbacks);
    }

    public function testGetAfterStageCallbacksReturnsEmptyForNonExistentPackage(): void
    {
        PackageLoader::initPackageInstances();

        $callbacks = PackageLoader::getAfterStageCallbacks('non-existent', 'stage');
        $this->assertEmpty($callbacks);
    }

    public function testRegisterAllDefaultStagesRegistersForPhpExtensions(): void
    {
        $this->createTestPackageConfig('test-ext', 'php-extension');
        PackageLoader::initPackageInstances();

        PackageLoader::registerAllDefaultStages();

        $package = PackageLoader::getPackage('test-ext');
        $this->assertInstanceOf(PhpExtensionPackage::class, $package);
        // Default stages should be registered (we can't easily verify this without accessing internal state)
    }

    public function testLoadFromPsr4DirLoadsAllClasses(): void
    {
        $this->createTestPackageConfig('test-lib', 'library');

        // Create a PSR-4 directory structure
        $psr4Dir = $this->tempDir . '/PackageClasses';
        mkdir($psr4Dir, 0755, true);

        // Create test class file
        $classContent = '<?php
namespace Test\Package;

use StaticPHP\Attribute\Package\Library;

#[Library("test-lib")]
class TestPackage1 {
}';
        file_put_contents($psr4Dir . '/TestPackage1.php', $classContent);

        // Load with auto_require enabled
        PackageLoader::loadFromPsr4Dir($psr4Dir, 'Test\Package', true);

        $this->assertTrue(PackageLoader::hasPackage('test-lib'));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createTestPackageConfig(string $name, string $type): void
    {
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');
        $property->setAccessible(true);
        $configs = $property->getValue();
        $configs[$name] = [
            'type' => $type,
            'deps' => [],
        ];
        $property->setValue(null, $configs);
    }
}
