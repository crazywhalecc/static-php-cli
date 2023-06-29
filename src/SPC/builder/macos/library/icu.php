<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\builder\unix\library\icu as icuTrait;

class icu extends MacOSLibraryBase
{
    use icuTrait;

    public const NAME = 'icu';

    public string $os = 'MacOSX';
}
