<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\unix\library\icu as icuTrait;

class icu extends LinuxLibraryBase
{
    use icuTrait;

    public const NAME = 'icu';

    public string $os = 'Linux';
}
