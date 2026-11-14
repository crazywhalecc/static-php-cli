<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Artifact;

use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\Type\Git;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Util\FileSystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
class GitReleaseTest extends TestCase
{
    private string $directory;

    private string $name;

    private false|string $allowedProtocols;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/spc-git-release-' . bin2hex(random_bytes(6));
        $this->name = basename($this->directory) . '-source';
        $this->allowedProtocols = getenv('GIT_ALLOW_PROTOCOL');
        putenv('GIT_ALLOW_PROTOCOL=file');
        mkdir($this->directory . '/repo', 0755, true);
        $this->git('init', '-b', 'main');
        $this->git('config', 'user.name', 'SPC Test');
        $this->git('config', 'user.email', 'test@example.com');
        file_put_contents($this->directory . '/repo/source.txt', 'stable');
        $this->git('add', '.');
        $this->git('commit', '-m', 'Initial source');
    }

    protected function tearDown(): void
    {
        putenv($this->allowedProtocols === false ? 'GIT_ALLOW_PROTOCOL' : 'GIT_ALLOW_PROTOCOL=' . $this->allowedProtocols);
        foreach ([$this->directory, sys_get_temp_dir() . '/' . $this->name, DOWNLOAD_PATH . '/' . $this->name] as $path) {
            if (is_dir($path)) {
                FileSystem::removeDir($path);
            }
        }
    }

    public function testStableTagsRetainSubmodulesAndDetectUpdates(): void
    {
        mkdir($this->directory . '/dependency');
        (new Process(['git', 'init', '-b', 'main'], $this->directory . '/dependency'))->mustRun();
        file_put_contents($this->directory . '/dependency/header.h', 'dependency');
        (new Process(['git', 'add', '.'], $this->directory . '/dependency'))->mustRun();
        (new Process(['git', '-c', 'user.name=SPC Test', '-c', 'user.email=test@example.com', 'commit', '-m', 'Dependency'], $this->directory . '/dependency'))->mustRun();
        $this->git('submodule', 'add', $this->directory . '/dependency', 'third_party/dependency');
        $this->git('commit', '-m', 'Add dependency');
        $this->git('tag', 'v1.9.0');
        $this->git('tag', '-a', 'v1.10.0', '-m', 'Stable release');
        $this->git('tag', 'v2.0.0-rc1');
        $this->git('tag', 'v3-snapshot');
        $this->git('branch', 'v9.0.0');
        file_put_contents($this->directory . '/repo/source.txt', 'development');
        $this->git('commit', '-am', 'Unreleased changes');

        $config = ['url' => $this->directory . '/repo', 'tag-regex' => 'v(?<version>\d+\.\d+\.\d+)', 'submodules' => ['third_party/dependency']];
        $downloader = new ArtifactDownloader([], false);
        $git = new Git();
        $update = $git->checkUpdate($this->name, $config, 'dev-main+1234567', $downloader);
        $this->assertSame('1.10.0', $update->new);
        $this->assertTrue($update->needUpdate);
        $this->assertFalse($git->checkUpdate($this->name, $config, '1.10.0', $downloader)->needUpdate);
        $result = $git->download($this->name, $config, $downloader);
        $this->assertSame('1.10.0', $result->version);
        $this->assertSame('stable', file_get_contents(DOWNLOAD_PATH . '/' . $this->name . '/source.txt'));
        $this->assertFileExists(DOWNLOAD_PATH . '/' . $this->name . '/third_party/dependency/header.h');

        $this->git('tag', 'v1.10.1');
        $this->assertSame('1.10.1', $git->checkUpdate($this->name, $config, '1.10.0', $downloader)->new);
    }

    public function testBranchRegexStillSelectsOnlyBranches(): void
    {
        $this->git('branch', 'v1.2.0');
        $this->git('tag', 'v9.0.0');
        $config = ['url' => $this->directory . '/repo', 'regex' => 'v(?<version>\d+\.\d+\.\d+)', 'submodules' => []];
        $downloader = new ArtifactDownloader([], false);
        $git = new Git();
        $this->assertSame('1.2.0', $git->checkUpdate($this->name, $config, null, $downloader)->new);
        $this->assertSame('1.2.0', $git->download($this->name, $config, $downloader)->version);
    }

    public function testMissingStableTagDoesNotFallBackToDevelopment(): void
    {
        $this->git('tag', 'v2.0.0-rc1');
        $this->git('branch', 'v1.0.0');
        $config = ['url' => $this->directory . '/repo', 'tag-regex' => 'v(?<version>\d+\.\d+\.\d+)'];
        $this->expectException(DownloaderException::class);
        $this->expectExceptionMessage('No matching tags ref');
        (new Git())->checkUpdate($this->name, $config, null, new ArtifactDownloader([], false));
    }

    private function git(string ...$arguments): void
    {
        (new Process(['git', ...$arguments], $this->directory . '/repo', ['GIT_ALLOW_PROTOCOL' => 'file']))->mustRun();
    }
}
