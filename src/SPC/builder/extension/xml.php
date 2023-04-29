<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('xml')]
#[CustomExt('soap')]
#[CustomExt('xmlreader')]
#[CustomExt('xmlwriter')]
#[CustomExt('dom')]
#[CustomExt('simplexml')]
class xml extends Extension
{
    /**
     * @throws RuntimeException
     */
    public function getUnixConfigureArg(): string
    {
        $arg = match ($this->name) {
            'xml' => '--enable-xml',
            'soap' => '--enable-soap',
            'xmlreader' => '--enable-xmlreader',
            'xmlwriter' => '--enable-xmlwriter',
            'dom' => '--enable-dom',
            'simplexml' => '--enable-simplexml',
            default => throw new RuntimeException('Not accept non-xml extension'),
        };
        $arg .= ' --with-libxml="' . BUILD_ROOT_PATH . '"';
        return $arg;
    }
}
