<?php

declare(strict_types=1);

namespace SPC\builder\traits;

use SPC\exception\SPCException;
use SPC\store\FileSystem;
use SPC\util\PkgConfigUtil;

trait openssl
{
    public function getLibVersion(): ?string
    {
        // get openssl version from source directory
        if (file_exists("{$this->source_dir}/VERSION.dat")) {
            // parse as INI
            $version = parse_ini_file("{$this->source_dir}/VERSION.dat");
            if ($version !== false) {
                return "{$version['MAJOR']}.{$version['MINOR']}.{$version['PATCH']}";
            }
        }
        // get openssl version from pkg-config
        if (PHP_OS_FAMILY !== 'Windows') {
            try {
                return PkgConfigUtil::getModuleVersion('openssl');
            } catch (SPCException) {
            }
        }
        // get openssl version from header openssl/opensslv.h
        if (file_exists(BUILD_INCLUDE_PATH . '/openssl/opensslv.h')) {
            if (preg_match('/OPENSSL_VERSION_STR "(.*)"/', FileSystem::readFile(BUILD_INCLUDE_PATH . '/openssl/opensslv.h'), $match)) {
                return $match[1];
            }
        }
        return null;
    }
}
