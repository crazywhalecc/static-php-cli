<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('sodium')]
class sodium extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // bypass error: unknown warning option '-Wno-logical-op' for macOS
        return $this->removeLineContainingString();
    }

    private function removeLineContainingString(): bool
    {
        $path = SOURCE_PATH . '/php-src/ext/sodium/config.m4';
        $search = '-Wno-logical-op';
        if (!file_exists($path)) {
            return false;
        }
        $content = file_get_contents($path);
        $lines = preg_split('/\r\n|\n/', $content);
        $filteredLines = array_filter($lines, function ($line) use ($search) {
            return strpos($line, $search) === false;
        });
        $newContent = implode("\n", $filteredLines);
        file_put_contents($path, $newContent);
        return true;
    }
}
