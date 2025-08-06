<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\exception\ValidationException;
use SPC\util\CustomExt;

#[CustomExt('mbregex')]
class mbregex extends Extension
{
    public function getDistName(): string
    {
        return 'mbstring';
    }

    /**
     * mbregex is not an extension, we need to overwrite the default check.
     */
    public function runCliCheckUnix(): void
    {
        $sharedext = $this->builder->getExt('mbstring')->isBuildShared() ? '-d "extension_dir=' . BUILD_MODULES_PATH . '" -d "extension=mbstring"' : '';
        [$ret] = shell()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n' . $sharedext . ' --ri "mbstring" | grep regex', false);
        if ($ret !== 0) {
            throw new ValidationException("Extension {$this->getName()} failed compile check: compiled php-cli mbstring extension does not contain regex !");
        }
    }

    public function runCliCheckWindows(): void
    {
        [$ret, $out] = cmd()->execWithResult(BUILD_ROOT_PATH . '/bin/php -n  --ri "mbstring"', false);
        if ($ret !== 0) {
            throw new ValidationException("extension {$this->getName()} failed compile check: compiled php-cli does not contain mbstring !");
        }
        $out = implode("\n", $out);
        if (!str_contains($out, 'regex')) {
            throw new ValidationException("extension {$this->getName()} failed compile check: compiled php-cli mbstring extension does not contain regex !");
        }
    }
}
