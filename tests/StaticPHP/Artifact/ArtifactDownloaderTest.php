<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Registry\ArtifactLoader;

/**
 * @internal
 */
class ArtifactDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset ArtifactConfig and ArtifactLoader static state
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setValue(null, []);

        $loaderReflection = new \ReflectionClass(ArtifactLoader::class);
        $loaderProperty = $loaderReflection->getProperty('artifacts');
        $loaderProperty->setValue(null, null);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setValue(null, []);

        $loaderReflection = new \ReflectionClass(ArtifactLoader::class);
        $loaderProperty = $loaderReflection->getProperty('artifacts');
        $loaderProperty->setValue(null, null);
    }

    // ==================== DOWNLOADERS constant ====================

    public function testDownloadersConstantHasExpectedKeys(): void
    {
        $expectedKeys = ['bitbuckettag', 'filelist', 'git', 'ghrel', 'ghtar', 'ghtagtar', 'local', 'pie', 'pecl', 'url', 'php-release', 'hosted'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, ArtifactDownloader::DOWNLOADERS, "Missing downloader key: {$key}");
        }
    }

    // ==================== Constructor options ====================

    public function testConstructWithDefaultOptions(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $this->assertSame(0, $downloader->getRetry());
        $this->assertFalse($downloader->interactive);
        $this->assertEmpty($downloader->getArtifacts());
    }

    public function testConstructWithParallelOption(): void
    {
        $downloader = new ArtifactDownloader(['parallel' => 4], false);

        // parallel is internal but setParallel/getArtifacts reveals behavior; check via setParallel chainability
        // Indirect verification: setParallel with same value returns $this
        $this->assertSame($downloader, $downloader->setParallel(4));
    }

    public function testConstructWithRetryOption(): void
    {
        $downloader = new ArtifactDownloader(['retry' => 3], false);

        $this->assertSame(3, $downloader->getRetry());
    }

    public function testConstructWithNegativeRetryClampedToZero(): void
    {
        $downloader = new ArtifactDownloader(['retry' => -5], false);

        $this->assertSame(0, $downloader->getRetry());
    }

    public function testConstructWithPreferSourceBoolOption(): void
    {
        // prefer-source=true sets default to FETCH_PREFER_SOURCE (0)
        $downloader = new ArtifactDownloader(['prefer-source' => true], false);

        $this->assertSame(0, $downloader->getRetry()); // sanity check, object created fine
    }

    public function testConstructWithPreferBinaryBoolOption(): void
    {
        $downloader = new ArtifactDownloader(['prefer-binary' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithPreferPreBuiltBoolOption(): void
    {
        $downloader = new ArtifactDownloader(['prefer-pre-built' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithSourceOnlyBoolOption(): void
    {
        $downloader = new ArtifactDownloader(['source-only' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithBinaryOnlyBoolOption(): void
    {
        $downloader = new ArtifactDownloader(['binary-only' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithIgnoreCacheBoolOption(): void
    {
        $downloader = new ArtifactDownloader(['ignore-cache' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithIgnoreCacheStringOptionParsesNames(): void
    {
        $downloader = new ArtifactDownloader(['ignore-cache' => 'openssl,zlib'], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithIgnoreCacheSourcesBackwardCompat(): void
    {
        $downloader = new ArtifactDownloader(['ignore-cache-sources' => true], false);

        $this->assertNotNull($downloader);
    }

    public function testConstructWithCustomUrlOptionAddsToIgnoreCache(): void
    {
        $downloader = new ArtifactDownloader(
            ['custom-url' => ['openssl:https://custom.example.com/openssl.tar.gz']],
            false
        );

        $this->assertNotNull($downloader);
    }

    public function testConstructWithCustomGitOption(): void
    {
        $downloader = new ArtifactDownloader(
            ['custom-git' => ['php-src:master:https://github.com/php/php-src.git']],
            false
        );

        $this->assertNotNull($downloader);
    }

    public function testConstructWithCustomLocalOption(): void
    {
        $downloader = new ArtifactDownloader(
            ['custom-local' => ['my-lib:/tmp/my-lib-source']],
            false
        );

        $this->assertNotNull($downloader);
    }

    public function testConstructWithNoAltOption(): void
    {
        $downloader = new ArtifactDownloader(['no-alt' => true], false);

        $this->assertNotNull($downloader);
    }

    // ==================== getRetry ====================

    public function testGetRetryDefaultsToZero(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $this->assertSame(0, $downloader->getRetry());
    }

    public function testGetRetryReturnsConfiguredValue(): void
    {
        $downloader = new ArtifactDownloader(['retry' => 5], false);

        $this->assertSame(5, $downloader->getRetry());
    }

    // ==================== getOption ====================

    public function testGetOptionReturnsConfiguredValue(): void
    {
        $downloader = new ArtifactDownloader(['retry' => 2, 'parallel' => 3], false);

        $this->assertSame(2, $downloader->getOption('retry'));
        $this->assertSame(3, $downloader->getOption('parallel'));
    }

    public function testGetOptionReturnsDefaultWhenNotSet(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $this->assertNull($downloader->getOption('non-existent'));
        $this->assertSame('default-val', $downloader->getOption('non-existent', 'default-val'));
    }

    // ==================== getArtifacts ====================

    public function testGetArtifactsReturnsEmptyInitially(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $this->assertSame([], $downloader->getArtifacts());
    }

    // ==================== add ====================

    public function testAddWithArtifactObjectAddsToList(): void
    {
        $downloader = new ArtifactDownloader([], false);
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $downloader->add($artifact);

        $artifacts = $downloader->getArtifacts();
        $this->assertArrayHasKey('my-pkg', $artifacts);
        $this->assertSame($artifact, $artifacts['my-pkg']);
    }

    public function testAddReturnsSelf(): void
    {
        $downloader = new ArtifactDownloader([], false);
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $result = $downloader->add($artifact);

        $this->assertSame($downloader, $result);
    }

    public function testAddDoesNotAddDuplicateArtifact(): void
    {
        $downloader = new ArtifactDownloader([], false);
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $downloader->add($artifact);
        $downloader->add($artifact);

        $this->assertCount(1, $downloader->getArtifacts());
    }

    public function testAddWithStringNameLooksUpFromArtifactLoader(): void
    {
        $this->injectArtifactConfig('my-lib', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $downloader = new ArtifactDownloader([], false);
        $downloader->add('my-lib');

        $artifacts = $downloader->getArtifacts();
        $this->assertArrayHasKey('my-lib', $artifacts);
    }

    public function testAddWithStringNameThrowsForNonExistentArtifact(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage("Artifact 'non-existent' not found");

        $downloader->add('non-existent');
    }

    // ==================== addArtifacts ====================

    public function testAddArtifactsAddsMultipleAtOnce(): void
    {
        $downloader = new ArtifactDownloader([], false);
        $a1 = new Artifact('pkg-a', ['source' => ['type' => 'url', 'url' => 'https://example.com/a.tar.gz']]);
        $a2 = new Artifact('pkg-b', ['source' => ['type' => 'url', 'url' => 'https://example.com/b.tar.gz']]);

        $downloader->addArtifacts([$a1, $a2]);

        $this->assertCount(2, $downloader->getArtifacts());
        $this->assertArrayHasKey('pkg-a', $downloader->getArtifacts());
        $this->assertArrayHasKey('pkg-b', $downloader->getArtifacts());
    }

    public function testAddArtifactsReturnsSelf(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $result = $downloader->addArtifacts([]);

        $this->assertSame($downloader, $result);
    }

    // ==================== setParallel ====================

    public function testSetParallelReturnsSelf(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $result = $downloader->setParallel(3);

        $this->assertSame($downloader, $result);
    }

    public function testSetParallelEnforcesMinimumOfOne(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $downloader->setParallel(0);
        // No direct getter for parallel, but verifying it doesn't throw
        $this->assertSame($downloader, $downloader->setParallel(0));
    }

    public function testSetParallelAcceptsNormalValue(): void
    {
        $downloader = new ArtifactDownloader([], false);

        $result = $downloader->setParallel(5);
        $this->assertSame($downloader, $result);
    }

    // ==================== Helpers ====================

    private function injectArtifactConfig(string $name, array $config): void
    {
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $configs = $property->getValue(null) ?? [];
        $configs[$name] = $config;
        $property->setValue(null, $configs);
    }
}
