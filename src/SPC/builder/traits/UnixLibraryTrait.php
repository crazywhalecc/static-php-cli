<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\builder\LibraryBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait UnixLibraryTrait
{
    use LibraryTrait;

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true): string
    {
        $libs = [$this];
        if ($recursive) {
            array_unshift($libs, ...array_values($this->getDependencies(recursive: true)));
        }

        $sep = match ($style) {
            'autoconf' => ' ',
            'cmake' => ';',
            default => throw new RuntimeException('style only support autoconf and cmake'),
        };
        $ret = [];
        /** @var LibraryBase $lib */
        foreach ($libs as $lib) {
            $libFiles = [];
            foreach ($lib->getStaticLibs() as $name) {
                $name = str_replace(' ', '\ ', FileSystem::convertPath(BUILD_LIB_PATH . "/{$name}"));
                $name = str_replace('"', '\"', $name);
                $libFiles[] = $name;
            }
            array_unshift($ret, implode($sep, $libFiles));
        }
        return implode($sep, $ret);
    }

    public function makeAutoconfEnv(string $prefix = null): string
    {
        if ($prefix === null) {
            $prefix = str_replace('-', '_', strtoupper(static::NAME));
        }
        return $prefix . '_CFLAGS="-I' . BUILD_INCLUDE_PATH . '" ' .
            $prefix . '_LIBS="' . $this->getStaticLibFiles() . '"';
    }
}
