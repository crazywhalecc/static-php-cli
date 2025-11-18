<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class krb5 extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\krb5;

    public const NAME = 'krb5';
}
