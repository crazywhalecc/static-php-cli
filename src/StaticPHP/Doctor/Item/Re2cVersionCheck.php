<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Doctor\CheckResult;

class Re2cVersionCheck
{
    #[CheckItem('if re2c version >= 1.0.3', limit_os: 'Linux', level: 20)]
    #[CheckItem('if re2c version >= 1.0.3', limit_os: 'Darwin', level: 20)]
    public function checkRe2cVersion(): ?CheckResult
    {
        $ver = shell(false)->execWithResult('re2c --version', false);
        // match version: re2c X.X(.X)
        if ($ver[0] !== 0 || !preg_match('/re2c\s+(\d+\.\d+(\.\d+)?)/', $ver[1][0], $matches)) {
            return CheckResult::fail('Failed to get re2c version', 'build-re2c');
        }
        $version_string = $matches[1];
        if (version_compare($version_string, '1.0.3') < 0) {
            return CheckResult::fail('re2c version is too low (' . $version_string . ')', 'build-re2c');
        }
        return CheckResult::ok($version_string);
    }

    #[FixItem('build-re2c')]
    public function buildRe2c(): bool
    {
        // TODO: implement re2c build process
        return false;
    }
}
