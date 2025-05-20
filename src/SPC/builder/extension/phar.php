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
    public function patchBeforeSharedBuild(): bool
    {
        if (!$this->builder instanceof LinuxBuilder) {
            return false;
        }
        FileSystem::replaceFileStr(
            $this->source_dir . '/config.m4',
            ['$ext_dir/phar.1', '$ext_dir/phar.phar.1'],
            ['${ext_dir}phar.1', '${ext_dir}phar.phar.1']
        );
        return true;
    }
}
