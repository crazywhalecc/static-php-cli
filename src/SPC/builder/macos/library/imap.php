<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

class imap extends MacOSLibraryBase
{
    use \SPC\builder\unix\library\imap;

    public const NAME = 'imap';
}
