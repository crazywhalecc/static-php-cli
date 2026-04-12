<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Exception\SPCInternalException;

/**
 * @internal
 */
class ArtifactCacheTest extends TestCase
{
    private string $tempDir;

    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/artifact_cache_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->cacheFile = $this->tempDir . '/.cache.json';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    // ==================== Constructor ====================

    public function testConstructorCreatesFileWhenNotExists(): void
    {
        $this->assertFalse(file_exists($this->cacheFile));

        new ArtifactCache($this->cacheFile);

        $this->assertTrue(file_exists($this->cacheFile));
        $this->assertSame('[]', file_get_contents($this->cacheFile));
    }

    public function testConstructorReadsExistingCacheFile(): void
    {
        $existing = ['openssl' => ['source' => null, 'binary' => []]];
        file_put_contents($this->cacheFile, json_encode($existing));

        $cache = new ArtifactCache($this->cacheFile);

        $this->assertSame([], $cache->getAllBinaryInfo('openssl'));
    }

    public function testConstructorHandlesEmptyExistingFile(): void
    {
        file_put_contents($this->cacheFile, '');

        $cache = new ArtifactCache($this->cacheFile);

        $this->assertEmpty($cache->getCachedArtifactNames());
    }

    // ==================== isSourceDownloaded ====================

    public function testIsSourceDownloadedReturnsFalseWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isSourceDownloaded('non-existent'));
    }

    public function testIsSourceDownloadedReturnsFalseWhenCacheEntryHasNullSource(): void
    {
        file_put_contents($this->cacheFile, json_encode([
            'my-pkg' => ['source' => null, 'binary' => []],
        ]));
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isSourceDownloaded('my-pkg'));
    }

    public function testIsSourceDownloadedReturnsTrueForLocalTypeWhenDirExists(): void
    {
        $localDir = $this->tempDir . '/local-source';
        mkdir($localDir, 0755, true);

        $this->writeCacheData([
            'my-pkg' => [
                'source' => [
                    'lock_type' => 'source',
                    'cache_type' => 'local',
                    'dirname' => $localDir,
                    'extract' => null,
                    'hash' => null,
                    'time' => time(),
                ],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertTrue($cache->isSourceDownloaded('my-pkg'));
    }

    public function testIsSourceDownloadedReturnsFalseForLocalTypeWhenDirNotExists(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => [
                    'lock_type' => 'source',
                    'cache_type' => 'local',
                    'dirname' => '/non/existent/path/xyz',
                    'extract' => null,
                    'hash' => null,
                    'time' => time(),
                ],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isSourceDownloaded('my-pkg'));
    }

    public function testIsSourceDownloadedReturnsFalseForArchiveTypeWhenFileNotExists(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => [
                    'lock_type' => 'source',
                    'cache_type' => 'archive',
                    'filename' => 'non-existent-file.tar.gz',
                    'extract' => null,
                    'hash' => 'abc123',
                    'time' => time(),
                ],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isSourceDownloaded('my-pkg'));
    }

    // ==================== isBinaryDownloaded ====================

    public function testIsBinaryDownloadedReturnsFalseWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isBinaryDownloaded('non-existent', 'linux-x86_64'));
    }

    public function testIsBinaryDownloadedReturnsFalseWhenPlatformNotCached(): void
    {
        $this->writeCacheData([
            'my-pkg' => ['source' => null, 'binary' => []],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertFalse($cache->isBinaryDownloaded('my-pkg', 'linux-x86_64'));
    }

    public function testIsBinaryDownloadedReturnsTrueForLocalTypeWhenDirExists(): void
    {
        $localDir = $this->tempDir . '/local-binary';
        mkdir($localDir, 0755, true);

        $this->writeCacheData([
            'my-pkg' => [
                'source' => null,
                'binary' => [
                    'linux-x86_64' => [
                        'lock_type' => 'binary',
                        'cache_type' => 'local',
                        'dirname' => $localDir,
                        'extract' => null,
                        'hash' => null,
                        'time' => time(),
                    ],
                ],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertTrue($cache->isBinaryDownloaded('my-pkg', 'linux-x86_64'));
    }

    // ==================== lock ====================

    public function testLockWithLocalSourceType(): void
    {
        $localDir = $this->tempDir . '/local-pkg';
        mkdir($localDir, 0755, true);

        $cache = new ArtifactCache($this->cacheFile);
        $downloadResult = DownloadResult::local($localDir, [], null, '1.0.0');

        $cache->lock('my-pkg', 'source', $downloadResult);

        $info = $cache->getSourceInfo('my-pkg');
        $this->assertNotNull($info);
        $this->assertSame('source', $info['lock_type']);
        $this->assertSame('local', $info['cache_type']);
        $this->assertSame($localDir, $info['dirname']);
    }

    public function testLockWithLocalBinaryTypePersistsCorrectPlatform(): void
    {
        $localDir = $this->tempDir . '/local-bin';
        mkdir($localDir, 0755, true);

        $cache = new ArtifactCache($this->cacheFile);
        $downloadResult = DownloadResult::local($localDir, [], null, '1.0.0');

        $cache->lock('my-pkg', 'binary', $downloadResult, 'linux-x86_64');

        $info = $cache->getBinaryInfo('my-pkg', 'linux-x86_64');
        $this->assertNotNull($info);
        $this->assertSame('binary', $info['lock_type']);
        $this->assertSame('linux-x86_64', $info['platform']);
    }

    public function testLockWithBinaryTypeThrowsWhenPlatformIsNull(): void
    {
        $localDir = $this->tempDir . '/local-bin2';
        mkdir($localDir, 0755, true);

        $cache = new ArtifactCache($this->cacheFile);
        $downloadResult = DownloadResult::local($localDir, [], null);

        $this->expectException(SPCInternalException::class);
        $cache->lock('my-pkg', 'binary', $downloadResult, null);
    }

    public function testLockPersistsCacheToFile(): void
    {
        $localDir = $this->tempDir . '/persist-test';
        mkdir($localDir, 0755, true);

        $cache = new ArtifactCache($this->cacheFile);
        $downloadResult = DownloadResult::local($localDir, []);

        $cache->lock('my-pkg', 'source', $downloadResult);

        // Read file contents to verify persisted
        $persisted = json_decode(file_get_contents($this->cacheFile), true);
        $this->assertArrayHasKey('my-pkg', $persisted);
        $this->assertNotNull($persisted['my-pkg']['source']);
    }

    // ==================== getSourceInfo ====================

    public function testGetSourceInfoReturnsNullWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertNull($cache->getSourceInfo('non-existent'));
    }

    public function testGetSourceInfoReturnsNullWhenSourceIsNull(): void
    {
        $this->writeCacheData(['my-pkg' => ['source' => null, 'binary' => []]]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertNull($cache->getSourceInfo('my-pkg'));
    }

    public function testGetSourceInfoReturnsData(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => ['lock_type' => 'source', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $info = $cache->getSourceInfo('my-pkg');
        $this->assertIsArray($info);
        $this->assertSame('local', $info['cache_type']);
    }

    // ==================== getBinaryInfo ====================

    public function testGetBinaryInfoReturnsNullWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertNull($cache->getBinaryInfo('non-existent', 'linux-x86_64'));
    }

    public function testGetBinaryInfoReturnsDataForPlatform(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => null,
                'binary' => [
                    'linux-x86_64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                ],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $info = $cache->getBinaryInfo('my-pkg', 'linux-x86_64');
        $this->assertIsArray($info);
        $this->assertSame('local', $info['cache_type']);
    }

    public function testGetBinaryInfoReturnsNullForDifferentPlatform(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => null,
                'binary' => [
                    'linux-x86_64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                ],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertNull($cache->getBinaryInfo('my-pkg', 'macos-aarch64'));
    }

    // ==================== getAllBinaryInfo ====================

    public function testGetAllBinaryInfoReturnsEmptyWhenNone(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertSame([], $cache->getAllBinaryInfo('non-existent'));
    }

    public function testGetAllBinaryInfoReturnsAllPlatforms(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => null,
                'binary' => [
                    'linux-x86_64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                    'macos-aarch64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                ],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $all = $cache->getAllBinaryInfo('my-pkg');
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('linux-x86_64', $all);
        $this->assertArrayHasKey('macos-aarch64', $all);
    }

    // ==================== getCacheFullPath ====================

    public function testGetCacheFullPathForArchiveType(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $info = ['cache_type' => 'archive', 'filename' => 'openssl-3.0.tar.gz'];

        $expected = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . 'openssl-3.0.tar.gz';
        $this->assertSame($expected, $cache->getCacheFullPath($info));
    }

    public function testGetCacheFullPathForGitType(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $info = ['cache_type' => 'git', 'dirname' => 'my-repo'];

        $expected = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . 'my-repo';
        $this->assertSame($expected, $cache->getCacheFullPath($info));
    }

    public function testGetCacheFullPathForLocalType(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $info = ['cache_type' => 'local', 'dirname' => '/absolute/path/to/dir'];

        $this->assertSame('/absolute/path/to/dir', $cache->getCacheFullPath($info));
    }

    public function testGetCacheFullPathForFileType(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $info = ['cache_type' => 'file', 'filename' => 'some-tool.exe'];

        $expected = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . 'some-tool.exe';
        $this->assertSame($expected, $cache->getCacheFullPath($info));
    }

    public function testGetCacheFullPathThrowsForUnknownType(): void
    {
        $cache = new ArtifactCache($this->cacheFile);
        $info = ['cache_type' => 'unknown-type'];

        $this->expectException(SPCInternalException::class);
        $cache->getCacheFullPath($info);
    }

    // ==================== removeSource ====================

    public function testRemoveSourceIsNoOpWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        // Should not throw
        $cache->removeSource('non-existent');
        $this->assertNull($cache->getSourceInfo('non-existent'));
    }

    public function testRemoveSourceRemovesCacheEntry(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => ['lock_type' => 'source', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $cache->removeSource('my-pkg');

        $this->assertNull($cache->getSourceInfo('my-pkg'));
    }

    public function testRemoveSourcePersistsToFile(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => ['lock_type' => 'source', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                'binary' => [],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $cache->removeSource('my-pkg');

        $persisted = json_decode(file_get_contents($this->cacheFile), true);
        $this->assertNull($persisted['my-pkg']['source']);
    }

    // ==================== removeBinary ====================

    public function testRemoveBinaryIsNoOpWhenNotCached(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        // Should not throw
        $cache->removeBinary('non-existent', 'linux-x86_64');
        $this->assertNull($cache->getBinaryInfo('non-existent', 'linux-x86_64'));
    }

    public function testRemoveBinaryRemovesPlatformEntry(): void
    {
        $this->writeCacheData([
            'my-pkg' => [
                'source' => null,
                'binary' => [
                    'linux-x86_64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                    'macos-aarch64' => ['lock_type' => 'binary', 'cache_type' => 'local', 'dirname' => '/tmp', 'extract' => null, 'hash' => null, 'time' => 0],
                ],
            ],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $cache->removeBinary('my-pkg', 'linux-x86_64');

        $this->assertNull($cache->getBinaryInfo('my-pkg', 'linux-x86_64'));
        // Other platform should still be there
        $this->assertNotNull($cache->getBinaryInfo('my-pkg', 'macos-aarch64'));
    }

    // ==================== getCachedArtifactNames ====================

    public function testGetCachedArtifactNamesReturnsEmptyWhenNoCacheFile(): void
    {
        $cache = new ArtifactCache($this->cacheFile);

        $this->assertSame([], $cache->getCachedArtifactNames());
    }

    public function testGetCachedArtifactNamesReturnsAllNames(): void
    {
        $this->writeCacheData([
            'openssl' => ['source' => null, 'binary' => []],
            'zlib' => ['source' => null, 'binary' => []],
            'brotli' => ['source' => null, 'binary' => []],
        ]);
        $cache = new ArtifactCache($this->cacheFile);

        $names = $cache->getCachedArtifactNames();
        $this->assertCount(3, $names);
        $this->assertContains('openssl', $names);
        $this->assertContains('zlib', $names);
        $this->assertContains('brotli', $names);
    }

    // ==================== save ====================

    public function testSavePersistsInMemoryCacheToFile(): void
    {
        $localDir = $this->tempDir . '/save-test-dir';
        mkdir($localDir, 0755, true);

        $cache = new ArtifactCache($this->cacheFile);
        // Lock an artifact so cache has data
        $downloadResult = DownloadResult::local($localDir, []);
        $cache->lock('my-pkg', 'source', $downloadResult);

        // Overwrite cache file to simulate external change
        file_put_contents($this->cacheFile, json_encode([]));

        // Save should re-write in-memory state
        $cache->save();

        $persisted = json_decode(file_get_contents($this->cacheFile), true);
        $this->assertArrayHasKey('my-pkg', $persisted);
    }

    // ==================== Helpers ====================

    private function writeCacheData(array $data): void
    {
        file_put_contents($this->cacheFile, json_encode($data));
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
