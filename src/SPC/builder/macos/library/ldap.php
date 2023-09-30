<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class ldap extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\ldap;

    public const NAME = 'ldap';
}
