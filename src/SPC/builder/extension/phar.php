<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\linux\LinuxBuilder;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('phar')]
class phar extends Extension
{
    public function buildUnixShared(): void
    {
        if (!$this->builder instanceof LinuxBuilder) {
            parent::buildUnixShared();
            return;
        }

        FileSystem::replaceFileStr(
            $this->source_dir . '/config.m4',
            ['$ext_dir/phar.1', '$ext_dir/phar.phar.1'],
            ['${ext_dir}phar.1', '${ext_dir}phar.phar.1']
        );
        try {
            parent::buildUnixShared();
        } finally {
            FileSystem::replaceFileStr(
                $this->source_dir . '/config.m4',
                ['${ext_dir}phar.1', '${ext_dir}phar.phar.1'],
                ['$ext_dir/phar.1', '$ext_dir/phar.phar.1']
            );
        }
    }
}
