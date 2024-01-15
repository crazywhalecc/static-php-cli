<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

/**
 * is a template library class for unix
 */
class libui_ng extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libui_ng;

    public const NAME = 'libui-ng';
}
