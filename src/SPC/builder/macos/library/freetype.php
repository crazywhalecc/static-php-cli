<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * is a template library class for unix
 */
class freetype extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\freetype;

    public const NAME = 'freetype';
}
