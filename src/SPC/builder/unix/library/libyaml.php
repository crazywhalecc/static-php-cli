<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\builder\unix\executor\UnixCMakeExecutor;
use SPC\store\FileSystem;

trait libyaml
{
    public function getLibVersion(): ?string
    {
        // Match version from CMakeLists.txt:
        // Format: set (YAML_VERSION_MAJOR 0)
        // set (YAML_VERSION_MINOR 2)
        // set (YAML_VERSION_PATCH 5)
        $content = FileSystem::readFile($this->source_dir . '/CMakeLists.txt');
        if (preg_match('/set \(YAML_VERSION_MAJOR (\d+)\)/', $content, $major)
            && preg_match('/set \(YAML_VERSION_MINOR (\d+)\)/', $content, $minor)
            && preg_match('/set \(YAML_VERSION_PATCH (\d+)\)/', $content, $patch)) {
            return "{$major[1]}.{$minor[1]}.{$patch[1]}";
        }
        return null;
    }

    protected function build(): void
    {
        UnixCMakeExecutor::create($this)->addConfigureArgs('-DBUILD_TESTING=OFF')->build();
    }
}
