<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
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

    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/win32/build/config.w32', 'dllmain.c ', '');
        return true;
    }

    public function getWindowsConfigureArg(): string
    {
        $arg = match ($this->name) {
            'xml' => '--with-xml',
            'soap' => '--enable-soap',
            'xmlreader' => '--enable-xmlreader',
            'xmlwriter' => '--enable-xmlwriter',
            'dom' => '--with-dom',
            'simplexml' => '--with-simplexml',
            default => throw new RuntimeException('Not accept non-xml extension'),
        };
        $arg .= ' --with-libxml';
        return $arg;
    }
}
