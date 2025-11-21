<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class krb5 extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\krb5;

    public const NAME = 'krb5';
}
