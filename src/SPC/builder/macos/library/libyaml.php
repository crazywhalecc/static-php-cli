<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class libyaml extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\libyaml;

    public const NAME = 'libyaml';
}
