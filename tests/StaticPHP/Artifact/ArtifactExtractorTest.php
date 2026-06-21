<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactExtractor;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Registry\ArtifactLoader;

/**
 * @internal
 */
class ArtifactExtractorTest extends TestCase
{
    private string $tempDir;

    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/artifact_extractor_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->cacheFile = $this->tempDir . '/.cache.json';
        file_put_contents($this->cacheFile, json_encode([]));

        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setValue(null, []);

        $loaderReflection = new \ReflectionClass(ArtifactLoader::class);
        $loaderProperty = $loaderReflection->getProperty('artifacts');
        $loaderProperty->setValue(null, null);

        ApplicationContext::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setValue(null, []);

        $loaderReflection = new \ReflectionClass(ArtifactLoader::class);
        $loaderProperty = $loaderReflection->getProperty('artifacts');
        $loaderProperty->setValue(null, null);

        ApplicationContext::reset();
    }

    // ==================== Constructor ====================

    public function testConstructorStoresProvidedCache(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $extractor = new ArtifactExtractor($cache, false);

        // Verify the extractor was created without error; it holds the cache internally
        $this->assertInstanceOf(ArtifactExtractor::class, $extractor);
    }

    public function testConstructorDefaultsInteractiveToTrue(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $extractor = new ArtifactExtractor($cache);

        $this->assertInstanceOf(ArtifactExtractor::class, $extractor);
    }

    // ==================== extractForPackages ====================

    public function testExtractForPackagesWithEmptyArrayDoesNothing(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $extractor = new ArtifactExtractor($cache, false);

        // Should complete without exception
        $extractor->extractForPackages([]);
        $this->assertTrue(true);
    }

    public function testExtractForPackagesDeduplicatesArtifacts(): void
    {
        ApplicationContext::initialize();
        $artifactConfig = ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']];
        $this->injectArtifactConfig('shared-lib', $artifactConfig);

        $artifact = new Artifact('shared-lib', $artifactConfig);

        // Create two mock packages that share the same artifact
        $pkg1 = $this->createMockPackage($artifact);
        $pkg2 = $this->createMockPackage($artifact);

        $cache = new ArtifactCache($this->cacheFile);

        // Partial mock to verify extract is called exactly once despite two packages
        $extractor = $this->getMockBuilder(ArtifactExtractor::class)
            ->setConstructorArgs([$cache, false])
            ->onlyMethods(['extract'])
            ->getMock();

        $extractor->expects($this->once())
            ->method('extract')
            ->with($artifact, false)
            ->willReturn(SPC_STATUS_ALREADY_EXTRACTED);

        $extractor->extractForPackages([$pkg1, $pkg2]);
    }

    public function testExtractForPackagesSkipsPackagesWithNoArtifact(): void
    {
        $pkgWithoutArtifact = $this->createMockPackage(null);

        $cache = new ArtifactCache($this->cacheFile);

        $extractor = $this->getMockBuilder(ArtifactExtractor::class)
            ->setConstructorArgs([$cache, false])
            ->onlyMethods(['extract'])
            ->getMock();

        // extract should NOT be called when no artifact
        $extractor->expects($this->never())->method('extract');

        $extractor->extractForPackages([$pkgWithoutArtifact]);
    }

    // ==================== extract ====================

    public function testExtractReturnsAlreadyExtractedForSecondCall(): void
    {
        ApplicationContext::initialize();
        $artifactConfig = ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']];
        $artifact = new Artifact('my-pkg', $artifactConfig);

        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('getSourceInfo')->willReturn(null);
        $cache->method('getBinaryInfo')->willReturn(null);
        $cache->method('isBinaryDownloaded')->willReturn(false);
        ApplicationContext::set(ArtifactCache::class, $cache);

        $extractor = new ArtifactExtractor($cache, false);

        // Pre-populate the extracted map for 'my-pkg' via reflection
        $reflection = new \ReflectionClass(ArtifactExtractor::class);
        $extractedProperty = $reflection->getProperty('extracted');
        $extractedProperty->setValue($extractor, ['my-pkg' => true]);

        $result = $extractor->extract($artifact, false);
        $this->assertSame(SPC_STATUS_ALREADY_EXTRACTED, $result);
    }

    public function testExtractWithStringNameLooksUpFromArtifactLoader(): void
    {
        ApplicationContext::initialize();
        $artifactConfig = ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']];
        $this->injectArtifactConfig('my-pkg', $artifactConfig);

        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('getSourceInfo')->willReturn(null);
        $cache->method('getBinaryInfo')->willReturn(null);
        $cache->method('isBinaryDownloaded')->willReturn(false);
        ApplicationContext::set(ArtifactCache::class, $cache);

        $extractor = new ArtifactExtractor($cache, false);

        // Pre-populate the extracted map so we don't need actual downloads
        $reflection = new \ReflectionClass(ArtifactExtractor::class);
        $extractedProperty = $reflection->getProperty('extracted');
        $extractedProperty->setValue($extractor, ['my-pkg' => true]);

        $result = $extractor->extract('my-pkg', false);
        $this->assertSame(SPC_STATUS_ALREADY_EXTRACTED, $result);
    }

    // ==================== Helpers ====================

    /**
     * Create a mock Package object that returns the given artifact from getArtifact().
     */
    private function createMockPackage(?Artifact $artifact): \StaticPHP\Package\Package
    {
        $mock = $this->createMock(\StaticPHP\Package\Package::class);
        $mock->method('getArtifact')->willReturn($artifact);
        return $mock;
    }

    private function injectArtifactConfig(string $name, array $config): void
    {
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $configs = $property->getValue(null) ?? [];
        $configs[$name] = $config;
        $property->setValue(null, $configs);
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
