<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Config;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Exception\WrongUsageException;

/**
 * @internal
 */
class ArtifactConfigTest extends TestCase
{
    private string $tempDir;

    /** @noinspection PhpExpressionResultUnusedInspection */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/artifact_config_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset static state
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    /** @noinspection PhpExpressionResultUnusedInspection */
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Reset static state
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function testLoadFromDirThrowsExceptionWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Directory /nonexistent/path does not exist, cannot load artifact config.');

        ArtifactConfig::loadFromDir('/nonexistent/path');
    }

    public function testLoadFromDirWithValidArtifactJson(): void
    {
        $artifactContent = json_encode([
            'test-artifact' => [
                'source' => 'https://example.com/file.tar.gz',
            ],
        ]);

        file_put_contents($this->tempDir . '/artifact.json', $artifactContent);

        ArtifactConfig::loadFromDir($this->tempDir);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('source', $config);
    }

    public function testLoadFromDirWithMultipleArtifactFiles(): void
    {
        $artifact1Content = json_encode([
            'artifact-1' => [
                'source' => 'https://example.com/file1.tar.gz',
            ],
        ]);

        $artifact2Content = json_encode([
            'artifact-2' => [
                'source' => 'https://example.com/file2.tar.gz',
            ],
        ]);

        file_put_contents($this->tempDir . '/artifact.ext.json', $artifact1Content);
        file_put_contents($this->tempDir . '/artifact.lib.json', $artifact2Content);
        file_put_contents($this->tempDir . '/artifact.json', json_encode(['artifact-3' => ['source' => 'custom']]));

        ArtifactConfig::loadFromDir($this->tempDir);

        $this->assertNotNull(ArtifactConfig::get('artifact-1'));
        $this->assertNotNull(ArtifactConfig::get('artifact-2'));
        $this->assertNotNull(ArtifactConfig::get('artifact-3'));
    }

    public function testLoadFromFileThrowsExceptionWhenFileCannotBeRead(): void
    {
        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Failed to read artifact config file:');

        ArtifactConfig::loadFromFile('/nonexistent/file.json');
    }

    public function testLoadFromFileThrowsExceptionWhenJsonIsInvalid(): void
    {
        $file = $this->tempDir . '/invalid.json';
        file_put_contents($file, 'not valid json{');

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('Invalid JSON format in artifact config file:');

        ArtifactConfig::loadFromFile($file);
    }

    public function testLoadFromFileWithValidJson(): void
    {
        $file = $this->tempDir . '/valid.json';
        $content = json_encode([
            'my-artifact' => [
                'source' => [
                    'type' => 'url',
                    'url' => 'https://example.com/file.tar.gz',
                ],
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('my-artifact');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('source', $config);
    }

    public function testGetAllReturnsAllLoadedArtifacts(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'artifact-a' => ['source' => 'custom'],
            'artifact-b' => ['source' => 'custom'],
            'artifact-c' => ['source' => 'custom'],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $all = ArtifactConfig::getAll();
        $this->assertIsArray($all);
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('artifact-a', $all);
        $this->assertArrayHasKey('artifact-b', $all);
        $this->assertArrayHasKey('artifact-c', $all);
    }

    public function testGetReturnsNullWhenArtifactNotFound(): void
    {
        $this->assertNull(ArtifactConfig::get('non-existent-artifact'));
    }

    public function testGetReturnsConfigWhenArtifactExists(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'test-artifact' => [
                'source' => 'custom',
                'binary' => 'custom',
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config);
        $this->assertEquals('custom', $config['source']);
        $this->assertIsArray($config['binary']);
    }

    public function testLoadFromFileWithExpandedUrlInSource(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'test-artifact' => [
                'source' => 'https://example.com/archive.tar.gz',
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config);
        $this->assertIsArray($config['source']);
        $this->assertEquals('url', $config['source']['type']);
        $this->assertEquals('https://example.com/archive.tar.gz', $config['source']['url']);
    }

    public function testLoadFromFileWithBinaryCustom(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'test-artifact' => [
                'source' => 'custom',
                'binary' => 'custom',
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config['binary']);
        $this->assertArrayHasKey('linux-x86_64', $config['binary']);
        $this->assertArrayHasKey('macos-aarch64', $config['binary']);
        $this->assertEquals('custom', $config['binary']['linux-x86_64']['type']);
    }

    public function testLoadFromFileWithBinaryHosted(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'test-artifact' => [
                'source' => 'custom',
                'binary' => 'hosted',
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config['binary']);
        $this->assertEquals('hosted', $config['binary']['linux-x86_64']['type']);
        $this->assertEquals('hosted', $config['binary']['macos-aarch64']['type']);
    }

    public function testLoadFromFileWithBinaryPlatformSpecific(): void
    {
        $file = $this->tempDir . '/artifacts.json';
        $content = json_encode([
            'test-artifact' => [
                'source' => 'custom',
                'binary' => [
                    'linux-x86_64' => 'https://example.com/linux.tar.gz',
                    'macos-aarch64' => [
                        'type' => 'url',
                        'url' => 'https://example.com/macos.tar.gz',
                    ],
                ],
            ],
        ]);
        file_put_contents($file, $content);

        ArtifactConfig::loadFromFile($file);

        $config = ArtifactConfig::get('test-artifact');
        $this->assertIsArray($config['binary']);
        $this->assertEquals('url', $config['binary']['linux-x86_64']['type']);
        $this->assertEquals('https://example.com/linux.tar.gz', $config['binary']['linux-x86_64']['url']);
        $this->assertEquals('url', $config['binary']['macos-aarch64']['type']);
        $this->assertEquals('https://example.com/macos.tar.gz', $config['binary']['macos-aarch64']['url']);
    }

    public function testLoadFromDirWithEmptyDirectory(): void
    {
        // Empty directory should not throw exception
        ArtifactConfig::loadFromDir($this->tempDir);

        $this->assertEquals([], ArtifactConfig::getAll());
    }

    public function testMultipleLoadsAppendConfigs(): void
    {
        $file1 = $this->tempDir . '/artifact1.json';
        $file2 = $this->tempDir . '/artifact2.json';

        file_put_contents($file1, json_encode(['art1' => ['source' => 'custom']]));
        file_put_contents($file2, json_encode(['art2' => ['source' => 'custom']]));

        ArtifactConfig::loadFromFile($file1);
        ArtifactConfig::loadFromFile($file2);

        $all = ArtifactConfig::getAll();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('art1', $all);
        $this->assertArrayHasKey('art2', $all);
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
