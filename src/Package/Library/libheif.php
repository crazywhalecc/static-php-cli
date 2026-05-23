<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libheif')]
class libheif
{
    #[PatchBeforeBuild]
    public function patchBeforeBuild(LibraryPackage $lib): void
    {
        if (!str_contains(file_get_contents($lib->getSourceDir() . '/CMakeLists.txt'), 'libbrotlienc')) {
            FileSystem::replaceFileStr(
                $lib->getSourceDir() . '/CMakeLists.txt',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")',
                'list(APPEND REQUIRES_PRIVATE "libbrotlidec")' . "\n" . '        list(APPEND REQUIRES_PRIVATE "libbrotlienc")'
            );
        }
        // libheif 1.22+ ships a C-incompatible header: `struct heif_bad_pixel`
        $heif_properties = $lib->getSourceDir() . '/libheif/api/libheif/heif_properties.h';
        if (file_exists($heif_properties)
            && str_contains(file_get_contents($heif_properties), 'struct heif_bad_pixel { uint32_t row; uint32_t column; };')
        ) {
            FileSystem::replaceFileStr(
                $heif_properties,
                'struct heif_bad_pixel { uint32_t row; uint32_t column; };',
                'typedef struct heif_bad_pixel { uint32_t row; uint32_t column; } heif_bad_pixel;'
            );
        }
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib): void
    {
        UnixCMakeExecutor::create($lib)
            ->addConfigureArgs(
                '--preset=release',
                '-DWITH_EXAMPLES=OFF',
                '-DWITH_GDK_PIXBUF=OFF',
                '-DBUILD_TESTING=OFF',
                '-DWITH_LIBSHARPYUV=ON', // optional: libwebp
                '-DENABLE_PLUGIN_LOADING=OFF',
            )
            ->build();
        $lib->patchPkgconfPrefix(['libheif.pc']);
    }
}
