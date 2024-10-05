<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\exception\WrongUsageException;
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

    public function testDownloadGit()
    {
        Downloader::downloadGit('setup-static-php', 'https://github.com/static-php/setup-static-php.git', 'main');
        $this->assertTrue(true);

        // test keyboard interrupt
        try {
            Downloader::downloadGit('setup-static-php', 'https://github.com/static-php/setup-static-php.git', 'SIGINT');
        } catch (WrongUsageException $e) {
            $this->assertStringContainsString('interrupted', $e->getMessage());
            return;
        }
        $this->fail('Expected exception not thrown');
    }

    public function testDownloadFile()
    {
        Downloader::downloadFile('fake-file', 'https://fakecmd.com/curlDown', 'curlDown.exe');
        $this->assertTrue(true);

        // test keyboard interrupt
        try {
            Downloader::downloadFile('fake-file', 'https://fakecmd.com/curlDown', 'SIGINT');
        } catch (WrongUsageException $e) {
            $this->assertStringContainsString('interrupted', $e->getMessage());
            return;
        }
        $this->fail('Expected exception not thrown');
    }

    public function testLockSource()
    {
        Downloader::lockSource('fake-file', ['source_type' => 'archive', 'filename' => 'fake-file-name', 'move_path' => 'fake-path', 'lock_as' => 'fake-lock-as']);
        $this->assertFileExists(DOWNLOAD_PATH . '/.lock.json');
        $json = json_decode(file_get_contents(DOWNLOAD_PATH . '/.lock.json'), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('fake-file', $json);
        $this->assertArrayHasKey('source_type', $json['fake-file']);
        $this->assertArrayHasKey('filename', $json['fake-file']);
        $this->assertArrayHasKey('move_path', $json['fake-file']);
        $this->assertArrayHasKey('lock_as', $json['fake-file']);
        $this->assertEquals('archive', $json['fake-file']['source_type']);
        $this->assertEquals('fake-file-name', $json['fake-file']['filename']);
        $this->assertEquals('fake-path', $json['fake-file']['move_path']);
        $this->assertEquals('fake-lock-as', $json['fake-file']['lock_as']);
    }

    public function testGetLatestBitbucketTag()
    {
        $this->assertEquals(
            'abc.tar.gz',
            Downloader::getLatestBitbucketTag('abc', [
                'repo' => 'MATCHED/def',
            ])[1]
        );
        $this->assertEquals(
            'abc-1.0.0.tar.gz',
            Downloader::getLatestBitbucketTag('abc', [
                'repo' => 'abc/def',
            ])[1]
        );
    }

    public function testGetLatestGithubRelease()
    {
        $this->assertEquals(
            'ghreltest.tar.gz',
            Downloader::getLatestGithubRelease('ghrel', [
                'type' => 'ghrel',
                'repo' => 'ghreltest/ghrel',
                'match' => 'ghreltest.tar.gz',
            ])[1]
        );
    }

    public function testGetFromFileList()
    {
        $filelist = Downloader::getFromFileList('fake-filelist', [
            'url' => 'https://fakecmd.com/filelist',
            'regex' => '/href="(?<file>filelist-(?<version>[^"]+)\.tar\.xz)"/',
        ]);
        $this->assertIsArray($filelist);
        $this->assertEquals('filelist-4.7.0.tar.xz', $filelist[1]);
    }
}
