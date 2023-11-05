<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class librdkafka extends LinuxLibraryBase
{
    // TODO: Linux is buggy, see https://github.com/confluentinc/librdkafka/discussions/4495
    use \SPC\builder\unix\library\librdkafka;

    public const NAME = 'librdkafka';
}
