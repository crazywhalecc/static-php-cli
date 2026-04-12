<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;

/**
 * @internal
 */
class ArtifactTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/artifact_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset ArtifactConfig static state
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setAccessible(true);
        $property->setValue(null, []);

        // Reset DI container
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
        $property->setAccessible(true);
        $property->setValue(null, []);

        ApplicationContext::reset();
    }

    // ==================== Constants ====================

    public function testConstantValues(): void
    {
        $this->assertSame(0, Artifact::FETCH_PREFER_SOURCE);
        $this->assertSame(1, Artifact::FETCH_PREFER_BINARY);
        $this->assertSame(2, Artifact::FETCH_ONLY_SOURCE);
        $this->assertSame(3, Artifact::FETCH_ONLY_BINARY);
    }

    // ==================== Constructor ====================

    public function testConstructWithInlineConfig(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertSame('my-pkg', $artifact->getName());
    }

    public function testConstructFallsBackToArtifactConfig(): void
    {
        $this->injectArtifactConfig('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $artifact = new Artifact('my-pkg');
        $this->assertSame('my-pkg', $artifact->getName());
    }

    public function testConstructThrowsForNonExistentArtifact(): void
    {
        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage("Artifact 'non-existent' not found.");

        new Artifact('non-existent');
    }

    // ==================== getName ====================

    public function testGetName(): void
    {
        $artifact = new Artifact('openssl', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $this->assertSame('openssl', $artifact->getName());
    }

    // ==================== getDownloadConfig ====================

    public function testGetDownloadConfigReturnsSection(): void
    {
        $config = ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'], 'binary' => []];
        $artifact = new Artifact('my-pkg', $config);

        $this->assertSame(['type' => 'url', 'url' => 'https://example.com/file.tar.gz'], $artifact->getDownloadConfig('source'));
    }

    public function testGetDownloadConfigReturnsNullForMissingSection(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertNull($artifact->getDownloadConfig('non-existent'));
    }

    // ==================== hasSource ====================

    public function testHasSourceReturnsTrueWhenConfigHasSource(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertTrue($artifact->hasSource());
    }

    public function testHasSourceReturnsFalseWhenNoSource(): void
    {
        $artifact = new Artifact('my-pkg', ['binary' => []]);

        $this->assertFalse($artifact->hasSource());
    }

    public function testHasSourceReturnsTrueWithCustomCallback(): void
    {
        $artifact = new Artifact('my-pkg', ['binary' => []]);
        $artifact->setCustomSourceCallback(function () {});

        $this->assertTrue($artifact->hasSource());
    }

    // ==================== hasPlatformBinary ====================

    public function testHasPlatformBinaryReturnsTrueWhenConfigHasBinaryForCurrentPlatform(): void
    {
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [$platform => ['type' => 'url', 'url' => 'https://example.com/bin.tar.gz']],
        ]);

        $this->assertTrue($artifact->hasPlatformBinary());
    }

    public function testHasPlatformBinaryReturnsFalseWhenNoBinaryConfig(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertFalse($artifact->hasPlatformBinary());
    }

    public function testHasPlatformBinaryReturnsTrueWithCustomCallback(): void
    {
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $artifact->setCustomBinaryCallback($platform, function () {});

        $this->assertTrue($artifact->hasPlatformBinary());
    }

    // ==================== getBinaryPlatforms ====================

    public function testGetBinaryPlatformsReturnsConfiguredPlatforms(): void
    {
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [
                'linux-x86_64' => ['type' => 'url', 'url' => 'https://example.com/linux.tar.gz'],
                'macos-aarch64' => ['type' => 'url', 'url' => 'https://example.com/mac.tar.gz'],
            ],
        ]);

        $platforms = $artifact->getBinaryPlatforms();
        $this->assertContains('linux-x86_64', $platforms);
        $this->assertContains('macos-aarch64', $platforms);
    }

    public function testGetBinaryPlatformsExcludesCustomTypeWithoutCallback(): void
    {
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [
                'linux-x86_64' => ['type' => 'custom'],
            ],
        ]);

        // No custom callback registered, so custom-type platform should NOT be included
        $platforms = $artifact->getBinaryPlatforms();
        $this->assertNotContains('linux-x86_64', $platforms);
    }

    public function testGetBinaryPlatformsIncludesCustomTypeWhenCallbackRegistered(): void
    {
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [
                'linux-x86_64' => ['type' => 'custom'],
            ],
        ]);
        $artifact->setCustomBinaryCallback('linux-x86_64', function () {});

        $platforms = $artifact->getBinaryPlatforms();
        $this->assertContains('linux-x86_64', $platforms);
    }

    public function testGetBinaryPlatformsIncludesCustomCallbackPlatforms(): void
    {
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
        ]);
        $artifact->setCustomBinaryCallback('linux-x86_64', function () {});

        $platforms = $artifact->getBinaryPlatforms();
        $this->assertContains('linux-x86_64', $platforms);
    }

    // ==================== getSourceDir ====================

    public function testGetSourceDirDefaultsToSourcePathWithName(): void
    {
        $cache = $this->makeStubbedArtifactCache([]);
        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $expected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, SOURCE_PATH . '/my-pkg');
        $this->assertSame($expected, $artifact->getSourceDir());
    }

    public function testGetSourceDirWithRelativeExtractInConfig(): void
    {
        $cache = $this->makeStubbedArtifactCache([]);
        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz', 'extract' => 'my-pkg-1.0'],
        ]);

        $expected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, SOURCE_PATH . '/my-pkg-1.0');
        $this->assertSame($expected, $artifact->getSourceDir());
    }

    public function testGetSourceDirWithAbsoluteExtractInConfig(): void
    {
        $cache = $this->makeStubbedArtifactCache([]);
        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz', 'extract' => '/tmp/my-pkg-extract'],
        ]);

        $expected = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, '/tmp/my-pkg-extract');
        $this->assertSame($expected, $artifact->getSourceDir());
    }

    // ==================== getSourceRoot ====================

    public function testGetSourceRootDefaultsToSourceDir(): void
    {
        $cache = $this->makeStubbedArtifactCache([]);
        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertSame($artifact->getSourceDir(), $artifact->getSourceRoot());
    }

    public function testGetSourceRootUsesMetadataSourceRoot(): void
    {
        $cache = $this->makeStubbedArtifactCache([]);
        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'metadata' => ['source-root' => 'src'],
        ]);

        $expected = $artifact->getSourceDir() . DIRECTORY_SEPARATOR . 'src';
        $this->assertSame($expected, $artifact->getSourceRoot());
    }

    // ==================== getBinaryExtractConfig ====================

    public function testGetBinaryExtractConfigDefaultsToStandard(): void
    {
        putenv('EMULATE_PLATFORM=linux-x86_64');
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            // no binary for linux-x86_64
        ]);

        $config = $artifact->getBinaryExtractConfig();
        $this->assertSame('standard', $config['mode']);
        $this->assertSame(PKG_ROOT_PATH, $config['path']);
        putenv('EMULATE_PLATFORM');
    }

    public function testGetBinaryExtractConfigWithHostedExtractReturnsBuildRootPath(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $config = $artifact->getBinaryExtractConfig(['extract' => 'hosted']);
        $this->assertSame('standard', $config['mode']);
        $this->assertSame(BUILD_ROOT_PATH, $config['path']);
    }

    public function testGetBinaryExtractConfigWithRelativeExtractInCacheInfo(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $config = $artifact->getBinaryExtractConfig(['extract' => 'subdir']);
        $this->assertSame('standard', $config['mode']);
        $this->assertStringContainsString('subdir', $config['path']);
    }

    public function testGetBinaryExtractConfigWithAbsoluteExtractInCacheInfo(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $config = $artifact->getBinaryExtractConfig(['extract' => '/tmp/absolute-path']);
        $this->assertSame('standard', $config['mode']);
        $this->assertStringContainsString('absolute-path', $config['path']);
    }

    public function testGetBinaryExtractConfigWithArrayReturnsSelective(): void
    {
        putenv('EMULATE_PLATFORM=linux-x86_64');
        $fileMap = ['lib/libfoo.a' => '/usr/local/lib/libfoo.a'];
        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [
                'linux-x86_64' => ['type' => 'url', 'url' => 'https://example.com/bin.tar.gz', 'extract' => $fileMap],
            ],
        ]);

        $config = $artifact->getBinaryExtractConfig();
        $this->assertSame('selective', $config['mode']);
        $this->assertNull($config['path']);
        $this->assertSame($fileMap, $config['files']);
        putenv('EMULATE_PLATFORM');
    }

    // ==================== getBinaryDir ====================

    public function testGetBinaryDirDelegatesToGetBinaryExtractConfig(): void
    {
        putenv('EMULATE_PLATFORM=linux-x86_64');
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertSame(PKG_ROOT_PATH, $artifact->getBinaryDir());
        putenv('EMULATE_PLATFORM');
    }

    // ==================== Custom source callbacks ====================

    public function testSetAndGetCustomSourceCallback(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setCustomSourceCallback($cb);

        $this->assertSame($cb, $artifact->getCustomSourceCallback());
    }

    public function testGetCustomSourceCallbackReturnsNullWhenNotSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertNull($artifact->getCustomSourceCallback());
    }

    public function testSetAndGetCustomSourceCheckUpdateCallback(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setCustomSourceCheckUpdateCallback($cb);

        $this->assertSame($cb, $artifact->getCustomSourceCheckUpdateCallback());
    }

    public function testGetCustomSourceCheckUpdateCallbackReturnsNullWhenNotSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertNull($artifact->getCustomSourceCheckUpdateCallback());
    }

    // ==================== Custom binary callbacks ====================

    public function testSetAndGetCustomBinaryCallback(): void
    {
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setCustomBinaryCallback($platform, $cb);

        $this->assertSame($cb, $artifact->getCustomBinaryCallback());
    }

    public function testGetCustomBinaryCallbackReturnsNullWhenNotSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertNull($artifact->getCustomBinaryCallback());
    }

    public function testSetCustomBinaryCallbackThrowsForInvalidPlatform(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->expectException(ValidationException::class);
        $artifact->setCustomBinaryCallback('invalid-platform-string', function () {});
    }

    public function testSetAndGetCustomBinaryCheckUpdateCallback(): void
    {
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setCustomBinaryCheckUpdateCallback($platform, $cb);

        $this->assertSame($cb, $artifact->getCustomBinaryCheckUpdateCallback());
    }

    public function testSetCustomBinaryCheckUpdateCallbackThrowsForInvalidPlatform(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->expectException(ValidationException::class);
        $artifact->setCustomBinaryCheckUpdateCallback('bad-platform', function () {});
    }

    // ==================== Source extract callbacks ====================

    public function testSetAndGetSourceExtractCallback(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setSourceExtractCallback($cb);

        $this->assertSame($cb, $artifact->getSourceExtractCallback());
    }

    public function testHasSourceExtractCallbackReturnsFalseWhenNotSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertFalse($artifact->hasSourceExtractCallback());
    }

    public function testHasSourceExtractCallbackReturnsTrueWhenSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $artifact->setSourceExtractCallback(function () {});

        $this->assertTrue($artifact->hasSourceExtractCallback());
    }

    // ==================== Binary extract callbacks ====================

    public function testSetAndGetBinaryExtractCallbackForCurrentPlatform(): void
    {
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setBinaryExtractCallback($cb, [$platform]);

        $this->assertSame($cb, $artifact->getBinaryExtractCallback());
    }

    public function testGetBinaryExtractCallbackReturnsNullForNonMatchingPlatform(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        // Register callback for a platform that is definitely NOT the current one
        $otherPlatforms = array_diff(['linux-x86_64', 'linux-aarch64', 'macos-x86_64', 'macos-aarch64', 'windows-x86_64'], [$this->getCurrentPlatform()]);
        $artifact->setBinaryExtractCallback(function () {}, array_values($otherPlatforms));

        // Only returns null if none of the listed platforms match current
        // Since current platform is excluded, all remaining are "other"
        $this->assertNull($artifact->getBinaryExtractCallback());
    }

    public function testSetBinaryExtractCallbackWithEmptyPlatformsMatchesAll(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $cb = function () {};
        $artifact->setBinaryExtractCallback($cb, []);

        $this->assertSame($cb, $artifact->getBinaryExtractCallback());
    }

    public function testHasBinaryExtractCallbackReturnsFalseWhenNotSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->assertFalse($artifact->hasBinaryExtractCallback());
    }

    public function testHasBinaryExtractCallbackReturnsTrueWhenSet(): void
    {
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $artifact->setBinaryExtractCallback(function () {});

        $this->assertTrue($artifact->hasBinaryExtractCallback());
    }

    // ==================== After-extract callbacks ====================

    public function testEmitAfterSourceExtractCallsAllCallbacks(): void
    {
        ApplicationContext::initialize();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $called1 = false;
        $called2 = false;
        $artifact->addAfterSourceExtractCallback(function (string $target_path) use (&$called1) {
            $called1 = true;
        });
        $artifact->addAfterSourceExtractCallback(function (string $target_path) use (&$called2) {
            $called2 = true;
        });

        $artifact->emitAfterSourceExtract('/tmp/test-path');

        $this->assertTrue($called1);
        $this->assertTrue($called2);
    }

    public function testEmitAfterBinaryExtractCallsCallbackMatchingPlatform(): void
    {
        ApplicationContext::initialize();
        $platform = $this->getCurrentPlatform();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $called = false;
        $artifact->addAfterBinaryExtractCallback(function (string $target_path, string $platform) use (&$called) {
            $called = true;
        }, [$platform]);

        $artifact->emitAfterBinaryExtract('/tmp/test-path', $platform);

        $this->assertTrue($called);
    }

    public function testEmitAfterBinaryExtractSkipsCallbackForNonMatchingPlatform(): void
    {
        ApplicationContext::initialize();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $called = false;
        $artifact->addAfterBinaryExtractCallback(function () use (&$called) {
            $called = true;
        }, ['windows-x86_64']);

        $artifact->emitAfterBinaryExtract('/tmp/test-path', 'linux-x86_64');

        $this->assertFalse($called);
    }

    public function testEmitAfterBinaryExtractWithEmptyPlatformsCallsForAnyPlatform(): void
    {
        ApplicationContext::initialize();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $called = false;
        $artifact->addAfterBinaryExtractCallback(function () use (&$called) {
            $called = true;
        }, []);

        $artifact->emitAfterBinaryExtract('/tmp/test-path', 'linux-x86_64');

        $this->assertTrue($called);
    }

    // ==================== isSourceDownloaded / isBinaryDownloaded delegation ====================

    public function testIsSourceDownloadedDelegatesToArtifactCache(): void
    {
        $cache = $this->createMock(ArtifactCache::class);
        $cache->expects($this->once())
            ->method('isSourceDownloaded')
            ->with('my-pkg', false)
            ->willReturn(true);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $this->assertTrue($artifact->isSourceDownloaded());
    }

    public function testIsBinaryDownloadedDelegatesToArtifactCache(): void
    {
        $platform = $this->getCurrentPlatform();
        $cache = $this->createMock(ArtifactCache::class);
        $cache->expects($this->once())
            ->method('isBinaryDownloaded')
            ->with('my-pkg', $platform, false)
            ->willReturn(true);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $this->assertTrue($artifact->isBinaryDownloaded($platform));
    }

    // ==================== shouldUseBinary ====================

    public function testShouldUseBinaryReturnsFalseWhenNotDownloaded(): void
    {
        $platform = $this->getCurrentPlatform();
        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('isBinaryDownloaded')->willReturn(false);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [$platform => ['type' => 'url', 'url' => 'https://example.com/bin.tar.gz']],
        ]);
        $this->assertFalse($artifact->shouldUseBinary());
    }

    public function testShouldUseBinaryReturnsFalseWhenNoBinaryConfig(): void
    {
        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('isBinaryDownloaded')->willReturn(true);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);
        $this->assertFalse($artifact->shouldUseBinary());
    }

    public function testShouldUseBinaryReturnsTrueWhenDownloadedAndHasBinaryConfig(): void
    {
        $platform = $this->getCurrentPlatform();
        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('isBinaryDownloaded')->willReturn(true);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        $artifact = new Artifact('my-pkg', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
            'binary' => [$platform => ['type' => 'url', 'url' => 'https://example.com/bin.tar.gz']],
        ]);
        $this->assertTrue($artifact->shouldUseBinary());
    }

    // ==================== isSourceExtracted ====================

    public function testIsSourceExtractedReturnsFalseWhenDirNotExists(): void
    {
        $cache = $this->createMock(ArtifactCache::class);
        $cache->method('getSourceInfo')->willReturn(null);

        ApplicationContext::initialize();
        ApplicationContext::set(ArtifactCache::class, $cache);

        // Use an artifact whose source dir doesn't exist on disk
        $artifact = new Artifact('this-pkg-does-not-exist-on-disk-2xyz', [
            'source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz'],
        ]);
        $this->assertFalse($artifact->isSourceExtracted());
    }

    // ==================== emitCustomBinary ====================

    public function testEmitCustomBinaryThrowsWhenNoBinaryCallbackDefined(): void
    {
        ApplicationContext::initialize();
        $artifact = new Artifact('my-pkg', ['source' => ['type' => 'url', 'url' => 'https://example.com/file.tar.gz']]);

        $this->expectException(SPCInternalException::class);
        $artifact->emitCustomBinary();
    }

    // ==================== Helpers ====================

    private function getCurrentPlatform(): string
    {
        $emulated = getenv('EMULATE_PLATFORM');
        if ($emulated !== false) {
            return $emulated;
        }
        $os = match (PHP_OS_FAMILY) {
            'Darwin' => 'macos',
            'Windows' => 'windows',
            default => 'linux',
        };
        $arch = php_uname('m');
        if ($arch === 'arm64') {
            $arch = 'aarch64';
        }
        return "{$os}-{$arch}";
    }

    private function injectArtifactConfig(string $name, array $config): void
    {
        $reflection = new \ReflectionClass(ArtifactConfig::class);
        $property = $reflection->getProperty('artifact_configs');
        $property->setAccessible(true);
        $configs = $property->getValue(null) ?? [];
        $configs[$name] = $config;
        $property->setValue(null, $configs);
    }

    /**
     * Create a stub ArtifactCache that always returns null for source/binary info
     * and delegates isSourceDownloaded/isBinaryDownloaded to return false.
     */
    private function makeStubbedArtifactCache(array $sourceInfoMap): ArtifactCache
    {
        $cacheFile = $this->tempDir . '/test-cache.json';
        file_put_contents($cacheFile, json_encode([]));
        return new ArtifactCache($cacheFile);
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
