<?php

declare(strict_types=1);

namespace SPC\util;

use Nette\PhpGenerator\Printer;

class PhpPrinter extends Printer
{
    // indentation character can be replaced with a sequence of spaces
    public string $indentation = '    ';
}
