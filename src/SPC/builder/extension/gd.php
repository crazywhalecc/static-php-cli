<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('gd')]
class gd extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $arg = '--enable-gd';
        $arg .= $this->builder->getLib('freetype') ? ' --with-freetype' : '';
        $arg .= $this->builder->getLib('libjpeg') ? ' --with-jpeg' : '';
        $arg .= $this->builder->getLib('libwebp') ? ' --with-webp' : '';
        $arg .= $this->builder->getLib('libavif') ? ' --with-avif' : '';
        return $arg;
    }
}
