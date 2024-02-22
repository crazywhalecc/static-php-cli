<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

abstract class WindowsLibraryBase extends LibraryBase
{
    public function __construct(protected WindowsBuilder $builder)
    {
        parent::__construct();
    }

    public function getBuilder(): BuilderBase
    {
        return $this->builder;
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     * @throws WrongUsageException
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

    /**
     * Create a nmake wrapper file.
     *
     * @param  string              $content          nmake wrapper content
     * @param  string              $default_filename default nmake wrapper filename
     * @throws FileSystemException
     */
    public function makeNmakeWrapper(string $content, string $default_filename = ''): string
    {
        if ($default_filename === '') {
            $default_filename = $this->source_dir . '\nmake_wrapper.bat';
        }
        FileSystem::writeFile($default_filename, $content);
        return 'nmake_wrapper.bat';
    }
}
