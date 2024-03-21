<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class librdkafka extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\librdkafka;

    public const NAME = 'librdkafka';
}
