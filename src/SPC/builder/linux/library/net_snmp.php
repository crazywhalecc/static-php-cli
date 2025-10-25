<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

class net_snmp extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\net_snmp;

    public const NAME = 'net-snmp';
}
