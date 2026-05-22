<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\store\FileSystem;
use SPC\util\executor\UnixCMakeExecutor;

trait libheif
{
    public function patchBeforeBuild(): bool
    {
        $patched = false;
        if (!str_contains(file_get_contents($this->source_dir . '/CMakeLists.txt'), 'libbrotlienc')) {
            FileSystem::replaceFileStr(
                $this->source_dir . '/CMakeLists.txt',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")' . "\n" . '        list(APPEND REQUIRES_PRIVATE "libbrotlienc")'
            );
            $patched = true;
        }
        // libheif 1.22+ ships a C-incompatible header: `struct heif_bad_pixel`
        $heif_properties = $this->source_dir . '/libheif/api/libheif/heif_properties.h';
        if (file_exists($heif_properties)
            && str_contains(file_get_contents($heif_properties), 'struct heif_bad_pixel { uint32_t row; uint32_t column; };')
        ) {
            FileSystem::replaceFileStr(
                $heif_properties,
                'struct heif_bad_pixel { uint32_t row; uint32_t column; };',
                'typedef struct heif_bad_pixel { uint32_t row; uint32_t column; } heif_bad_pixel;'
            );
            $patched = true;
        }
        return $patched;
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
