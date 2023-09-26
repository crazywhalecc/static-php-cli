<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

/**
 * a template library class for unix
 */
class imap extends LinuxLibraryBase
{
    use \SPC\builder\unix\library\imap;

    public const NAME = 'imap';
}
