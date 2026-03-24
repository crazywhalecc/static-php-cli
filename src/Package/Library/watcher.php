<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Util\FileSystem;

#[Library('watcher')]
class watcher extends LibraryPackage
{
    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(): void
    {
        $cflags = $this->getLibExtraCXXFlags();
        if (stripos($cflags, '-fpic') === false) {
            $cflags .= ' -fPIC';
        }
        $ldflags = $this->getLibExtraLdFlags() ? ' ' . $this->getLibExtraLdFlags() : '';
        shell()->cd("{$this->getSourceDir()}/watcher-c")
            ->exec(getenv('CXX') . " -c -o libwatcher-c.o ./src/watcher-c.cpp -I ./include -I ../include -std=c++17 -Wall -Wextra {$cflags}{$ldflags}")
            ->exec(getenv('AR') . ' rcs libwatcher-c.a libwatcher-c.o');

        copy("{$this->getSourceDir()}/watcher-c/libwatcher-c.a", "{$this->getLibDir()}/libwatcher-c.a");
        FileSystem::createDir("{$this->getIncludeDir()}/wtr");
        copy("{$this->getSourceDir()}/watcher-c/include/wtr/watcher-c.h", "{$this->getIncludeDir()}/wtr/watcher-c.h");
    }
}
