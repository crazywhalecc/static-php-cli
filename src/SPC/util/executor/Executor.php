<?php

declare(strict_types=1);

namespace SPC\util\executor;

use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\LibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\macos\library\MacOSLibraryBase;

abstract class Executor
{
    public function __construct(protected BSDLibraryBase|LinuxLibraryBase|MacOSLibraryBase $library) {}

    public static function create(LibraryBase $library): static
    {
        return new static($library);
    }
}
