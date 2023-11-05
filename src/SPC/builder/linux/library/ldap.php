<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class ldap extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\ldap;

    public const NAME = 'ldap';
}
