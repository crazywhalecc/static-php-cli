<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\WindowsUtil;

#[Library('imagemagick')]
class imagemagick
{
    /**
     * Build a fully static, self-contained ImageMagick 7 (Q16-HDRI, /MT) on Windows using the
     * official VisualMagick build (the ImageMagick/Windows + Configure + Dependencies repos), which
     * bundles every delegate. ImageMagick has no autoconf/CMake build on Windows, so this clones the
     * VisualMagick tree, generates a static x64 solution via the Configure tool, and builds it with
     * msbuild. The resulting CORE_RL_*.lib static libraries + MagickWand/MagickCore headers are
     * installed into the build root for ext-imagick to link.
     *
     * The VisualMagick tree lives under source/ so bin/spc reset cleans it up like everything
     * else; override the location with SPC_IMAGEMAGICK_BUILD_DIR.
     */
    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $work = getenv('SPC_IMAGEMAGICK_BUILD_DIR') ?: SOURCE_PATH . '\imagemagick-win';
        $configure_release = '2026.05.30.2033';
        $configure_url = "https://github.com/ImageMagick/Configure/releases/download/{$configure_release}/Configure.Release.x64.exe";

        FileSystem::createDir($work);
        // Clone the VisualMagick repos (ImageMagick source + Configure + Dependencies + all delegates).
        if (!is_dir("{$work}\\ImageMagick")) {
            cmd()->cd($work)->exec(SPC_GIT_EXEC . ' clone --depth 1 https://github.com/ImageMagick/Windows.git .');
            cmd()->cd($work)->exec('bash clone-repositories.sh --imagemagick7');
        }
        // Use the prebuilt Configure tool (building it from source needs the MFC components).
        default_shell()->executeCurlDownload($configure_url, "{$work}\\Configure\\Configure.Release.x64.exe", retries: 2);

        // Generate a static, /MT (linkRuntime), x64, Q16-HDRI solution with the configs embedded
        // (zeroConfigurationSupport) and OpenMP off (no vcomp runtime dependency).
        $ver = WindowsUtil::findVisualStudio();
        $vs_major = is_array($ver) ? $ver['major_version'] : 'unknown';
        $vs_arg = match ($vs_major) {
            '18',
            '17' => '/VS2022',
            '16' => '/VS2019',
            default => throw new EnvironmentException("Current VS version {$vs_major} is not supported yet!"),
        };
        cmd()->cd("{$work}\\Configure")
            ->exec("Configure.Release.x64.exe /noWizard {$vs_arg} /x64 /static /linkRuntime /noOpenMP /zeroConfigurationSupport");

        // x64 IM7 defaults to a 64-bit channel mask, whose magick-baseconfig.h #errors unless the
        // consuming translation unit is C++. ext-imagick is plain C, so force a 32-bit channel mask
        // (ample: 32 channels >> RGBA/CMYK) before building, keeping libs and the installed header in sync.
        FileSystem::replaceFileStr(
            "{$work}\\ImageMagick\\MagickCore\\magick-baseconfig.h",
            '#define MAGICKCORE_CHANNEL_MASK_DEPTH 64',
            '#define MAGICKCORE_CHANNEL_MASK_DEPTH 32'
        );

        cmd()->cd($work)
            ->exec('msbuild IM7.Static.x64.sln /m /t:Rebuild /nologo /p:Configuration=Release /p:Platform=x64');

        $artifacts = "{$work}\\Artifacts\\lib";
        if (!is_dir($artifacts)) {
            throw new EnvironmentException('ImageMagick VisualMagick build produced no Artifacts/lib; build failed.');
        }
        // Install the static libs (flat, onto the build-root lib path) and the public headers.
        FileSystem::createDir($lib->getLibDir());
        foreach (glob("{$artifacts}\\CORE_RL_*.lib") as $f) {
            FileSystem::copy($f, $lib->getLibDir() . '\\' . basename($f));
        }
        foreach (['MagickWand', 'MagickCore'] as $dir) {
            FileSystem::createDir($lib->getIncludeDir() . "\\imagemagick\\{$dir}");
            foreach (glob("{$work}\\ImageMagick\\{$dir}\\*.h") as $h) {
                FileSystem::copy($h, $lib->getIncludeDir() . "\\imagemagick\\{$dir}\\" . basename($h));
            }
        }
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(LibraryPackage $lib, ToolchainInterface $toolchain): void
    {
        $ldflags = $original_ldflags = getenv('SPC_DEFAULT_LDFLAGS');
        if (str_contains($ldflags, '-Wl,--as-needed')) {
            $ldflags = str_replace('-Wl,--as-needed', '', $ldflags);
            f_putenv("SPC_DEFAULT_LDFLAGS={$ldflags}");
        }

        $ac = UnixAutoconfExecutor::create($lib)
            ->optionalPackage('libzip', ...ac_with_args('zip'))
            ->optionalPackage('libjpeg', ...ac_with_args('jpeg'))
            ->optionalPackage('libpng', ...ac_with_args('png'))
            ->optionalPackage('libwebp', ...ac_with_args('webp'))
            ->optionalPackage('libxml2', ...ac_with_args('xml'))
            ->optionalPackage('libheif', ...ac_with_args('heic'))
            ->optionalPackage('zlib', ...ac_with_args('zlib'))
            ->optionalPackage('xz', ...ac_with_args('lzma'))
            ->optionalPackage('zstd', ...ac_with_args('zstd'))
            ->optionalPackage('freetype', ...ac_with_args('freetype'))
            ->optionalPackage('bzip2', ...ac_with_args('bzlib'))
            ->optionalPackage('libjxl', ...ac_with_args('jxl'))
            ->optionalPackage('jbig', ...ac_with_args('jbig'))
            ->addConfigureArgs(
                '--disable-openmp',
                '--without-x',
                // implicit --with-gcc-arch
                // bleeds host cpu features into built binaries
                '--without-gcc-arch',
                '--disable-docs',
                '--without-utilities',
                '--disable-dpc',
            );

        // special: linux-static target needs `-static`
        $ldflags = $toolchain->isStatic() ? '-static -ldl' : '-ldl';

        // special: macOS needs -iconv
        $libs = SystemTarget::getTargetOS() === 'Darwin' ? '-liconv' : '';

        $ac->appendEnv([
            'LDFLAGS' => $ldflags,
            'LIBS' => $libs,
            'PKG_CONFIG' => '$PKG_CONFIG --static',
        ]);

        $ac->configure()->make();

        f_putenv("SPC_DEFAULT_LDFLAGS={$original_ldflags}");

        $filelist = [
            'ImageMagick.pc',
            'ImageMagick-7.Q16HDRI.pc',
            'Magick++.pc',
            'Magick++-7.Q16HDRI.pc',
            'MagickCore.pc',
            'MagickCore-7.Q16HDRI.pc',
            'MagickWand.pc',
            'MagickWand-7.Q16HDRI.pc',
        ];
        $lib->patchPkgconfPrefix($filelist);
        foreach ($filelist as $file) {
            FileSystem::replaceFileRegex(
                "{$lib->getLibDir()}/pkgconfig/{$file}",
                '#includearchdir=/include/ImageMagick-7#m',
                'includearchdir=${prefix}/include/ImageMagick-7'
            );
        }
        $lib->patchLaDependencyPrefix();
    }
}
