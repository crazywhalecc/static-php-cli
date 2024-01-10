<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

use SPC\builder\BuilderBase;
use SPC\builder\LibraryBase;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\FileSystemException;
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
