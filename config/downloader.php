<?php

declare(strict_types=1);

use StaticPHP\Artifact\Downloader\Type\BitBucketTag;
use StaticPHP\Artifact\Downloader\Type\DownloadTypeInterface;
use StaticPHP\Artifact\Downloader\Type\FileList;
use StaticPHP\Artifact\Downloader\Type\Git;
use StaticPHP\Artifact\Downloader\Type\GitHubRelease;
use StaticPHP\Artifact\Downloader\Type\GitHubTarball;
use StaticPHP\Artifact\Downloader\Type\HostedPackageBin;
use StaticPHP\Artifact\Downloader\Type\LocalDir;
use StaticPHP\Artifact\Downloader\Type\PhpRelease;
use StaticPHP\Artifact\Downloader\Type\PIE;
use StaticPHP\Artifact\Downloader\Type\Url;

/* @return array<string, DownloadTypeInterface> */
return [
    'bitbuckettag' => BitBucketTag::class,
    'filelist' => FileList::class,
    'git' => Git::class,
    'ghrel' => GitHubRelease::class,
    'ghtar' => GitHubTarball::class,
    'ghtagtar' => GitHubTarball::class,
    'local' => LocalDir::class,
    'pie' => PIE::class,
    'url' => Url::class,
    'php-release' => PhpRelease::class,
    'hosted' => HostedPackageBin::class,
];
