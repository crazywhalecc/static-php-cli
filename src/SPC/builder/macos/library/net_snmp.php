<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class net_snmp extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\net_snmp;

    public const NAME = 'net-snmp';
}
