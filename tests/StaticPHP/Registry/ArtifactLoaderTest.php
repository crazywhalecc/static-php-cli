<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Registry;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\Artifact\BinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\SourceExtract;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Registry\ArtifactLoader;

/**
 * @internal
 */
class ArtifactLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/artifact_loader_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset ArtifactLoader and ArtifactConfig state
        $reflection = new \ReflectionClass(ArtifactLoader::class);
        $property = $reflection->getProperty('artifacts');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $configReflection = new \ReflectionClass(ArtifactConfig::class);
        $configProperty = $configReflection->getProperty('artifact_configs');
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

        // Reset ArtifactLoader and ArtifactConfig state
        $reflection = new \ReflectionClass(ArtifactLoader::class);
        $property = $reflection->getProperty('artifacts');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $configReflection = new \ReflectionClass(ArtifactConfig::class);
        $configProperty = $configReflection->getProperty('artifact_configs');
        $configProperty->setAccessible(true);
        $configProperty->setValue(null, []);
    }

    public function testInitArtifactInstancesOnlyRunsOnce(): void
    {
        $this->createTestArtifactConfig('test-artifact');

        ArtifactLoader::initArtifactInstances();
        ArtifactLoader::initArtifactInstances();

        // Should only initialize once
        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertInstanceOf(Artifact::class, $artifact);
    }

    public function testGetArtifactInstanceReturnsNullForNonExistent(): void
    {
        ArtifactLoader::initArtifactInstances();
        $artifact = ArtifactLoader::getArtifactInstance('non-existent-artifact');
        $this->assertNull($artifact);
    }

    public function testGetArtifactInstanceReturnsArtifact(): void
    {
        $this->createTestArtifactConfig('test-artifact');

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertInstanceOf(Artifact::class, $artifact);
    }

    public function testLoadFromClassWithCustomSourceAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomSource('test-artifact')]
            public function customSource(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidCustomSourceArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomSource('non-existent-artifact')]
            public function customSource(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[CustomSource]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithCustomBinaryAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomBinary('test-artifact', ['linux-x86_64', 'macos-aarch64'])]
            public function customBinary(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidCustomBinaryArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomBinary('non-existent-artifact', ['linux-x86_64'])]
            public function customBinary(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[CustomBinary]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithSourceExtractAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[SourceExtract('test-artifact')]
            public function sourceExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidSourceExtractArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[SourceExtract('non-existent-artifact')]
            public function sourceExtract(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[SourceExtract]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithBinaryExtractAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[BinaryExtract('test-artifact')]
            public function binaryExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassWithBinaryExtractAttributeAndPlatforms(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[BinaryExtract('test-artifact', platforms: ['linux-x86_64', 'darwin-aarch64'])]
            public function binaryExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidBinaryExtractArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[BinaryExtract('non-existent-artifact')]
            public function binaryExtract(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[BinaryExtract]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithAfterSourceExtractAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[AfterSourceExtract('test-artifact')]
            public function afterSourceExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidAfterSourceExtractArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[AfterSourceExtract('non-existent-artifact')]
            public function afterSourceExtract(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[AfterSourceExtract]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithAfterBinaryExtractAttribute(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[AfterBinaryExtract('test-artifact')]
            public function afterBinaryExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassWithAfterBinaryExtractAttributeAndPlatforms(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[AfterBinaryExtract('test-artifact', platforms: ['linux-x86_64'])]
            public function afterBinaryExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassThrowsExceptionForInvalidAfterBinaryExtractArtifact(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[AfterBinaryExtract('non-existent-artifact')]
            public function afterBinaryExtract(): void {}
        };

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Artifact 'non-existent-artifact' not found for #[AfterBinaryExtract]");

        ArtifactLoader::loadFromClass(get_class($class));
    }

    public function testLoadFromClassWithMultipleAttributes(): void
    {
        $this->createTestArtifactConfig('test-artifact-1');
        $this->createTestArtifactConfig('test-artifact-2');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomSource('test-artifact-1')]
            public function customSource(): void {}

            #[CustomBinary('test-artifact-2', ['linux-x86_64'])]
            public function customBinary(): void {}

            #[SourceExtract('test-artifact-1')]
            public function sourceExtract(): void {}

            #[AfterSourceExtract('test-artifact-2')]
            public function afterSourceExtract(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact1 = ArtifactLoader::getArtifactInstance('test-artifact-1');
        $artifact2 = ArtifactLoader::getArtifactInstance('test-artifact-2');
        $this->assertNotNull($artifact1);
        $this->assertNotNull($artifact2);
    }

    public function testLoadFromClassIgnoresNonPublicMethods(): void
    {
        $this->createTestArtifactConfig('test-artifact');
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            #[CustomSource('test-artifact')]
            public function publicCustomSource(): void {}

            #[CustomSource('test-artifact')]
            private function privateCustomSource(): void {}

            #[CustomSource('test-artifact')]
            protected function protectedCustomSource(): void {}
        };

        // Should only process public method
        ArtifactLoader::loadFromClass(get_class($class));

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromPsr4DirLoadsAllClasses(): void
    {
        $this->createTestArtifactConfig('test-artifact');

        // Create a PSR-4 directory structure
        $psr4Dir = $this->tempDir . '/ArtifactClasses';
        mkdir($psr4Dir, 0755, true);

        // Create test class file
        $classContent = '<?php
namespace Test\Artifact;

use StaticPHP\Attribute\Artifact\CustomSource;

class TestArtifact1 {
    #[CustomSource("test-artifact")]
    public function customSource() {}
}';
        file_put_contents($psr4Dir . '/TestArtifact1.php', $classContent);

        // Load with auto_require enabled
        ArtifactLoader::loadFromPsr4Dir($psr4Dir, 'Test\Artifact', true);

        $artifact = ArtifactLoader::getArtifactInstance('test-artifact');
        $this->assertNotNull($artifact);
    }

    public function testLoadFromClassWithNoAttributes(): void
    {
        ArtifactLoader::initArtifactInstances();

        $class = new class {
            public function regularMethod(): void {}
        };

        // Should not throw exception
        ArtifactLoader::loadFromClass(get_class($class));

        // Verify no side effects
        $this->assertTrue(true);
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

    private function createTestArtifactConfig(string $name): void
    {
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setAccessible(true);
        $configs = $property->getValue();
        $configs[$name] = [
            'type' => 'source',
            'url' => 'https://example.com/test.tar.gz',
        ];
        $property->setValue(null, $configs);
    }
}
