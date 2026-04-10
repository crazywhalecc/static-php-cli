<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCTarget;

trait graphicsmagick
{
    protected function build(): void
    {
        $ac = UnixAutoconfExecutor::create($this)
            ->optionalLib('zlib', ...ac_with_args('zlib'))
            ->optionalLib('libpng', ...ac_with_args('png'))
            ->optionalLib('libjpeg', ...ac_with_args('jpeg'))
            ->optionalLib('libwebp', ...ac_with_args('webp'))
            ->optionalLib('libtiff', ...ac_with_args('tiff'))
            ->optionalLib('freetype', ...ac_with_args('ttf'))
            ->optionalLib('bzip2', ...ac_with_args('bzlib'))
            ->addConfigureArgs(
                '--disable-openmp',
                '--without-x',
                '--without-perl',
                '--enable-shared=no',
                '--enable-static=yes',
            );

        // special: linux-static target needs `-static`
        $ldflags = SPCTarget::isStatic() ? '-static -ldl' : '-ldl';

        // special: macOS needs -liconv
        $libs = SPCTarget::getTargetOS() === 'Darwin' ? '-liconv' : '';

        $ac->appendEnv([
            'LDFLAGS' => $ldflags,
            'LIBS' => $libs,
            'PKG_CONFIG' => '$PKG_CONFIG --static',
        ]);

        $ac->configure()->make();

        $this->patchPkgconfPrefix(['GraphicsMagick.pc', 'GraphicsMagick++.pc', 'GraphicsMagickWand.pc']);
        $this->patchLaDependencyPrefix();
    }
}
