<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\BuilderProvider;
use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\exception\DownloaderException;
use SPC\store\Config;
use SPC\store\Downloader;
use Symfony\Component\Console\Input\ArgvInput;

class Re2cVersionCheck
{
    use UnixSystemUtilTrait;

    #[AsCheckItem('if re2c version >= 1.0.3', limit_os: 'Linux', level: 20)]
    #[AsCheckItem('if re2c version >= 1.0.3', limit_os: 'Darwin', level: 20)]
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

    #[AsFixItem('build-re2c')]
    public function buildRe2c(): bool
    {
        try {
            Downloader::downloadSource('re2c');
        } catch (DownloaderException) {
            logger()->warning('Failed to download re2c version, trying alternative');
            $alt = Config::getSource('re2c');
            $alt = [...$alt, ...$alt['alt'] ?? []];
            Downloader::downloadSource('re2c', $alt);
        }
        $builder = BuilderProvider::makeBuilderByInput(new ArgvInput([]));
        $builder->proveLibs(['re2c']);
        $builder->setupLibs();
        return true;
    }
}
