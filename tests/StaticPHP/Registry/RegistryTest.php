<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Registry;

use PHPUnit\Framework\TestCase;
use StaticPHP\Exception\RegistryException;
use StaticPHP\Registry\Registry;

/**
 * @internal
 */
class RegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/registry_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset Registry state
        Registry::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Reset Registry state
        Registry::reset();
    }

    public function testLoadRegistryWithValidJsonFile(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'test-registry',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        Registry::loadRegistry($registryFile);

        $this->assertContains('test-registry', Registry::getLoadedRegistries());
    }

    public function testLoadRegistryWithValidYamlFile(): void
    {
        $registryFile = $this->tempDir . '/test-registry.yaml';
        $registryContent = "name: test-registry-yaml\npackage:\n  config: []";
        file_put_contents($registryFile, $registryContent);

        Registry::loadRegistry($registryFile);

        $this->assertContains('test-registry-yaml', Registry::getLoadedRegistries());
    }

    public function testLoadRegistryWithValidYmlFile(): void
    {
        $registryFile = $this->tempDir . '/test-registry.yml';
        $registryContent = "name: test-registry-yml\npackage:\n  config: []";
        file_put_contents($registryFile, $registryContent);

        Registry::loadRegistry($registryFile);

        $this->assertContains('test-registry-yml', Registry::getLoadedRegistries());
    }

    public function testLoadRegistryThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('Failed to read registry file');

        Registry::loadRegistry($this->tempDir . '/non-existent.json');
    }

    public function testLoadRegistryThrowsExceptionForUnsupportedFormat(): void
    {
        $registryFile = $this->tempDir . '/test-registry.txt';
        file_put_contents($registryFile, 'invalid content');

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('Unsupported registry file format');

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistryThrowsExceptionForInvalidJson(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        file_put_contents($registryFile, 'invalid json content');

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('Invalid registry format');

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistryThrowsExceptionForMissingName(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'package' => [],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage("Registry 'name' is missing or invalid");

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistryThrowsExceptionForEmptyName(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => '',
            'package' => [],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage("Registry 'name' is missing or invalid");

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistryThrowsExceptionForNonStringName(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 123,
            'package' => [],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage("Registry 'name' is missing or invalid");

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistrySkipsDuplicateRegistry(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'duplicate-registry',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        // Load first time
        Registry::loadRegistry($registryFile);
        $this->assertCount(1, Registry::getLoadedRegistries());

        // Load second time - should skip
        Registry::loadRegistry($registryFile);
        $this->assertCount(1, Registry::getLoadedRegistries());
    }

    public function testLoadFromEnvOrOptionWithNullRegistries(): void
    {
        // Should not throw exception when null is passed and env is not set
        Registry::loadFromEnvOrOption(null);
        $this->assertEmpty(Registry::getLoadedRegistries());
    }

    public function testLoadFromEnvOrOptionWithEmptyString(): void
    {
        Registry::loadFromEnvOrOption('');
        $this->assertEmpty(Registry::getLoadedRegistries());
    }

    public function testLoadFromEnvOrOptionWithSingleRegistry(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'env-test-registry',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        Registry::loadFromEnvOrOption($registryFile);

        $this->assertContains('env-test-registry', Registry::getLoadedRegistries());
    }

    public function testLoadFromEnvOrOptionWithMultipleRegistries(): void
    {
        $registryFile1 = $this->tempDir . '/test-registry-1.json';
        $registryData1 = [
            'name' => 'env-test-registry-1',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile1, json_encode($registryData1));

        $registryFile2 = $this->tempDir . '/test-registry-2.json';
        $registryData2 = [
            'name' => 'env-test-registry-2',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile2, json_encode($registryData2));

        Registry::loadFromEnvOrOption($registryFile1 . ':' . $registryFile2);

        $this->assertContains('env-test-registry-1', Registry::getLoadedRegistries());
        $this->assertContains('env-test-registry-2', Registry::getLoadedRegistries());
    }

    public function testLoadFromEnvOrOptionIgnoresNonExistentFiles(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'env-test-registry',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        // Mix existing and non-existing files
        Registry::loadFromEnvOrOption($registryFile . ':' . $this->tempDir . '/non-existent.json');

        // Should only load the existing one
        $this->assertCount(1, Registry::getLoadedRegistries());
        $this->assertContains('env-test-registry', Registry::getLoadedRegistries());
    }

    public function testGetLoadedRegistriesReturnsEmptyArrayInitially(): void
    {
        $this->assertEmpty(Registry::getLoadedRegistries());
    }

    public function testGetLoadedRegistriesReturnsCorrectList(): void
    {
        $registryFile1 = $this->tempDir . '/test-registry-1.json';
        $registryData1 = [
            'name' => 'registry-1',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile1, json_encode($registryData1));

        $registryFile2 = $this->tempDir . '/test-registry-2.json';
        $registryData2 = [
            'name' => 'registry-2',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile2, json_encode($registryData2));

        Registry::loadRegistry($registryFile1);
        Registry::loadRegistry($registryFile2);

        $loaded = Registry::getLoadedRegistries();
        $this->assertCount(2, $loaded);
        $this->assertContains('registry-1', $loaded);
        $this->assertContains('registry-2', $loaded);
    }

    public function testResetClearsLoadedRegistries(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'test-registry',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        Registry::loadRegistry($registryFile);
        $this->assertNotEmpty(Registry::getLoadedRegistries());

        Registry::reset();
        $this->assertEmpty(Registry::getLoadedRegistries());
    }

    public function testLoadRegistryWithAutoloadPath(): void
    {
        // Create a test autoload file
        $autoloadFile = $this->tempDir . '/vendor/autoload.php';
        mkdir(dirname($autoloadFile), 0755, true);
        file_put_contents($autoloadFile, '<?php // Test autoload');

        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'autoload-test-registry',
            'autoload' => 'vendor/autoload.php',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        // Should not throw exception
        Registry::loadRegistry($registryFile);

        $this->assertContains('autoload-test-registry', Registry::getLoadedRegistries());
    }

    public function testLoadRegistryWithNonExistentAutoloadPath(): void
    {
        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'autoload-missing-test-registry',
            'autoload' => 'vendor/non-existent-autoload.php',
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        // Should throw exception when path doesn't exist
        $this->expectException(RegistryException::class);
        $this->expectExceptionMessage('Path does not exist');

        Registry::loadRegistry($registryFile);
    }

    public function testLoadRegistryWithAbsoluteAutoloadPath(): void
    {
        // Create a test autoload file with absolute path
        $autoloadFile = $this->tempDir . '/vendor/autoload.php';
        mkdir(dirname($autoloadFile), 0755, true);
        file_put_contents($autoloadFile, '<?php // Test autoload');

        $registryFile = $this->tempDir . '/test-registry.json';
        $registryData = [
            'name' => 'absolute-autoload-test-registry',
            'autoload' => $autoloadFile,
            'package' => [
                'config' => [],
            ],
        ];
        file_put_contents($registryFile, json_encode($registryData));

        Registry::loadRegistry($registryFile);

        $this->assertContains('absolute-autoload-test-registry', Registry::getLoadedRegistries());
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
}
