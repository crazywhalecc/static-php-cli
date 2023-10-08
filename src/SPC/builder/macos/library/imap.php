<?php

declare(strict_types=1);

namespace SPC\builder\macos\library;

use SPC\exception\WrongUsageException;

class imap extends MacOSLibraryBase
{
    // patchBeforeBuild()
    use \SPC\builder\unix\library\imap;

    public const NAME = 'imap';

    protected function build(): void
    {
        if ($this->builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-imap is not thread safe, do not build it with ZTS builds');
        }

        // TODO: macOS support
    }
}
