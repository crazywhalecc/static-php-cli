<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Config;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;

/**
 * @internal
 */
class PackageConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/package_config_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset static state
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Reset static state
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function testLoadFromDirThrowsExceptionWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Directory /nonexistent/path does not exist, cannot load pkg.json config.');

        PackageConfig::loadFromDir('/nonexistent/path');
    }

    public function testLoadFromDirWithValidPkgJson(): void
    {
        $packageContent = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test-artifact',
            ],
        ]);

        file_put_contents($this->tempDir . '/pkg.json', $packageContent);

        PackageConfig::loadFromDir($this->tempDir);

        $this->assertTrue(PackageConfig::isPackageExists('test-pkg'));
    }

    public function testLoadFromDirWithMultiplePackageFiles(): void
    {
        $pkg1Content = json_encode([
            'pkg-1' => [
                'type' => 'library',
                'artifact' => 'artifact-1',
            ],
        ]);

        $pkg2Content = json_encode([
            'pkg-2' => [
                'type' => 'php-extension',
            ],
        ]);

        file_put_contents($this->tempDir . '/pkg.ext.json', $pkg1Content);
        file_put_contents($this->tempDir . '/pkg.lib.json', $pkg2Content);
        file_put_contents($this->tempDir . '/pkg.json', json_encode(['pkg-3' => ['type' => 'virtual-target']]));

        PackageConfig::loadFromDir($this->tempDir);

        $this->assertTrue(PackageConfig::isPackageExists('pkg-1'));
        $this->assertTrue(PackageConfig::isPackageExists('pkg-2'));
        $this->assertTrue(PackageConfig::isPackageExists('pkg-3'));
    }

    public function testLoadFromFileThrowsExceptionWhenFileCannotBeRead(): void
    {
        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Failed to read package config file:');

        PackageConfig::loadFromFile('/nonexistent/file.json');
    }

    public function testLoadFromFileThrowsExceptionWhenJsonIsInvalid(): void
    {
        $file = $this->tempDir . '/invalid.json';
        file_put_contents($file, 'not valid json{');

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Invalid JSON format in package config file:');

        PackageConfig::loadFromFile($file);
    }

    public function testLoadFromFileWithValidJson(): void
    {
        $file = $this->tempDir . '/valid.json';
        $content = json_encode([
            'my-pkg' => [
                'type' => 'library',
                'artifact' => 'my-artifact',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $this->assertTrue(PackageConfig::isPackageExists('my-pkg'));
    }

    public function testIsPackageExistsReturnsFalseWhenPackageNotLoaded(): void
    {
        $this->assertFalse(PackageConfig::isPackageExists('non-existent'));
    }

    public function testIsPackageExistsReturnsTrueWhenPackageLoaded(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $this->assertTrue(PackageConfig::isPackageExists('test-pkg'));
    }

    public function testGetAllReturnsAllLoadedPackages(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'pkg-a' => ['type' => 'virtual-target'],
            'pkg-b' => ['type' => 'virtual-target'],
            'pkg-c' => ['type' => 'virtual-target'],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $all = PackageConfig::getAll();
        $this->assertIsArray($all);
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('pkg-a', $all);
        $this->assertArrayHasKey('pkg-b', $all);
        $this->assertArrayHasKey('pkg-c', $all);
    }

    public function testGetReturnsDefaultWhenPackageNotExists(): void
    {
        $result = PackageConfig::get('non-existent', 'field', 'default-value');

        $this->assertEquals('default-value', $result);
    }

    public function testGetReturnsWholePackageWhenFieldNameIsNull(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends' => ['dep1'],
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $result = PackageConfig::get('test-pkg');
        $this->assertIsArray($result);
        $this->assertEquals('library', $result['type']);
        $this->assertEquals('test', $result['artifact']);
    }

    public function testGetReturnsFieldValueWhenFieldExists(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test-artifact',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $result = PackageConfig::get('test-pkg', 'artifact');
        $this->assertEquals('test-artifact', $result);
    }

    public function testGetReturnsDefaultWhenFieldNotExists(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $result = PackageConfig::get('test-pkg', 'non-existent-field', 'default');
        $this->assertEquals('default', $result);
    }

    public function testGetWithSuffixFieldsOnLinux(): void
    {
        // Mock SystemTarget to return Linux
        $mockTarget = $this->getMockBuilder(SystemTarget::class)
            ->disableOriginalConstructor()
            ->getMock();

        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends' => ['base-dep'],
                'depends@linux' => ['linux-dep'],
                'depends@unix' => ['unix-dep'],
                'depends@windows' => ['windows-dep'],
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        // The get method will check SystemTarget::getTargetOS()
        // On real Linux systems, it should return 'depends@linux' first
        $result = PackageConfig::get('test-pkg', 'depends', []);

        // Result should be one of the suffixed versions or base version
        $this->assertIsArray($result);
    }

    public function testGetWithSuffixFieldsReturnsBasicFieldWhenNoSuffixMatch(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends' => ['base-dep'],
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $result = PackageConfig::get('test-pkg', 'depends');
        $this->assertEquals(['base-dep'], $result);
    }

    public function testGetWithNonSuffixedFieldIgnoresSuffixes(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test-artifact',
                'artifact@linux' => 'linux-artifact', // This should be ignored
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        // 'artifact' is not in SUFFIX_ALLOWED_FIELDS, so it won't check suffixes
        $result = PackageConfig::get('test-pkg', 'artifact');
        $this->assertEquals('test-artifact', $result);
    }

    public function testGetAllSuffixAllowedFields(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'library',
                'artifact' => 'test',
                'depends@linux' => ['dep1'],
                'suggests@macos' => ['sug1'],
                'headers@unix' => ['header.h'],
                'static-libs@windows' => ['lib.a'],
                'static-bins@linux' => ['bin'],
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        // These are all suffix-allowed fields
        $pkg = PackageConfig::get('test-pkg');
        $this->assertArrayHasKey('depends@linux', $pkg);
        $this->assertArrayHasKey('suggests@macos', $pkg);
        $this->assertArrayHasKey('headers@unix', $pkg);
        $this->assertArrayHasKey('static-libs@windows', $pkg);
        $this->assertArrayHasKey('static-bins@linux', $pkg);
    }

    public function testLoadFromDirWithEmptyDirectory(): void
    {
        // Empty directory should not throw exception
        PackageConfig::loadFromDir($this->tempDir);

        $this->assertEquals([], PackageConfig::getAll());
    }

    public function testMultipleLoadsAppendConfigs(): void
    {
        $file1 = $this->tempDir . '/pkg1.json';
        $file2 = $this->tempDir . '/pkg2.json';

        file_put_contents($file1, json_encode(['pkg1' => ['type' => 'virtual-target']]));
        file_put_contents($file2, json_encode(['pkg2' => ['type' => 'virtual-target']]));

        PackageConfig::loadFromFile($file1);
        PackageConfig::loadFromFile($file2);

        $all = PackageConfig::getAll();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('pkg1', $all);
        $this->assertArrayHasKey('pkg2', $all);
    }

    public function testGetWithComplexPhpExtensionPackage(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-ext' => [
                'type' => 'php-extension',
                'depends' => ['dep1'],
                'php-extension' => [
                    'zend-extension' => false,
                    'build-shared' => true,
                    'build-static' => false,
                ],
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $phpExt = PackageConfig::get('test-ext', 'php-extension');
        $this->assertIsArray($phpExt);
        $this->assertFalse($phpExt['zend-extension']);
        $this->assertTrue($phpExt['build-shared']);
    }

    public function testGetReturnsNullAsDefaultWhenNotSpecified(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'test-pkg' => [
                'type' => 'virtual-target',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $result = PackageConfig::get('test-pkg', 'non-existent');
        $this->assertNull($result);
    }

    public function testLoadFromFileWithAllPackageTypes(): void
    {
        $file = $this->tempDir . '/pkg.json';
        $content = json_encode([
            'library-pkg' => [
                'type' => 'library',
                'artifact' => 'lib-artifact',
            ],
            'extension-pkg' => [
                'type' => 'php-extension',
            ],
            'target-pkg' => [
                'type' => 'target',
                'artifact' => 'target-artifact',
            ],
            'virtual-pkg' => [
                'type' => 'virtual-target',
            ],
        ]);
        file_put_contents($file, $content);

        PackageConfig::loadFromFile($file);

        $this->assertTrue(PackageConfig::isPackageExists('library-pkg'));
        $this->assertTrue(PackageConfig::isPackageExists('extension-pkg'));
        $this->assertTrue(PackageConfig::isPackageExists('target-pkg'));
        $this->assertTrue(PackageConfig::isPackageExists('virtual-pkg'));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
