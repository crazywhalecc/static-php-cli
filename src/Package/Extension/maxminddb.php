<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('maxminddb')]
class maxminddb extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-maxminddb')]
    #[PatchDescription('Patch maxminddb extension for buildconf to support new source structure')]
    public function patchBeforeBuildconf(): void
    {
        if (file_exists("{$this->getSourceDir()}/config.m4")) {
            return;
        }
        // move ext/maxminddb/ext/* to ext/maxminddb/
        $files = FileSystem::scanDirFiles("{$this->getSourceDir()}/ext", false, true);
        foreach ($files as $file) {
            rename("{$this->getSourceDir()}/ext/{$file}", "{$this->getSourceDir()}/{$file}");
        }
    }
}
