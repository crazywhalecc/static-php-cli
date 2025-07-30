<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

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
        return null;
    }
}
