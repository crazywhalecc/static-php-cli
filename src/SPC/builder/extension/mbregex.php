<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\RuntimeException;
use SPC\util\CustomExt;

#[CustomExt('mbregex')]
class mbregex extends Extension
{
    public function getDistName(): string
    {
        return 'mbstring';
    }

    public function getConfigureArg(): string
    {
        return '';
    }

    /**
     * mbregex is not an extension, we need to overwrite the default check.
     */
    public function runCliCheckUnix(): void
    {
        [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n --ri "mbstring" | grep regex', false);
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: compiled php-cli mbstring extension does not contain regex !');
        }
    }

    public function runCliCheckWindows(): void
    {
        [$ret, $out] = cmd()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n  --ri "mbstring"', false);
        if ($ret !== 0) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: compiled php-cli does not contain mbstring !');
        }
        $out = implode("\n", $out);
        if (!str_contains($out, 'regex')) {
            throw new RuntimeException('extension ' . $this->getName() . ' failed compile check: compiled php-cli mbstring extension does not contain regex !');
        }
    }
}
