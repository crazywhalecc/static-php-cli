<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\store\Downloader;

/**
 * @internal
 * TODO: Test all methods
 */
class DownloaderTest extends TestCase
{
    public function testGetLatestGithubTarball()
    {
        $this->assertEquals(
            'https://api.github.com/repos/AOMediaCodec/libavif/tarball/v1.1.1',
            Downloader::getLatestGithubTarball('libavif', [
                'type' => 'ghtar',
                'repo' => 'AOMediaCodec/libavif',
            ])[0]
        );
    }

    public function testDownloadGit() {}

    public function testDownloadFile() {}

    public function testLockSource() {}

    public function testGetLatestBitbucketTag() {}

    public function testGetLatestGithubRelease() {}

    public function testCurlExec() {}

    public function testCurlDown() {}

    public function testDownloadSource() {}

    public function testGetFromFileList() {}

    public function testDownloadPackage() {}
}
