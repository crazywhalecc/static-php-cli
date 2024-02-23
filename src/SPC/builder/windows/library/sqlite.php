<?php

declare(strict_types=1);

namespace SPC\builder\windows\library;

class sqlite extends WindowsLibraryBase
{
    public const NAME = 'sqlite';

    public function patchBeforeBuild(): bool
    {
        copy(ROOT_DIR . '/src/globals/extra/Makefile-sqlite', $this->source_dir . '/Makefile');
        return true;
    }

    protected function build(): void
    {
        cmd()->cd($this->source_dir)->execWithWrapper($this->builder->makeSimpleWrapper('nmake'), 'PREFIX=' . BUILD_ROOT_PATH . ' install-static');
    }
}
