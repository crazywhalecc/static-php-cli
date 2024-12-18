<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\store\FileSystem;

class libheif extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libheif;

    public const NAME = 'libheif';

    public function patchBeforeBuild(): bool
    {
        if (!str_contains(file_get_contents($this->source_dir . '/CMakeLists.txt'), 'libbrotlienc')) {
            FileSystem::replaceFileStr(
                $this->source_dir . '/CMakeLists.txt',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")' . "\n" . '        list(APPEND REQUIRES_PRIVATE "libbrotlienc")'
            );
            return true;
        }
        return false;
    }
}
