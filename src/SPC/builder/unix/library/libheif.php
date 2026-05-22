<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait libheif
{
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

    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '--preset=release',
                '-DWITH_EXAMPLES=OFF',
                '-DWITH_GDK_PIXBUF=OFF',
                '-DBUILD_TESTING=OFF',
                '-DWITH_LIBSHARPYUV=ON', // optional: libwebp
                '-DENABLE_PLUGIN_LOADING=OFF',
            )
            ->build();
        $this->patchPkgconfPrefix(['libheif.pc']);
    }
}
