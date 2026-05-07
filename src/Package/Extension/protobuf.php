<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\PackageInstaller;

#[Extension('protobuf')]
class protobuf
{
    #[Validate]
    public function validate(PackageInstaller $installer): void
    {
        if (php::getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new ValidationException('The latest protobuf extension requires PHP 8.0 or later');
        }
        $grpc = $installer->getPhpExtensionPackage('ext-grpc');
        // protobuf conflicts with grpc
        if ($grpc?->isBuildStatic()) {
            throw new ValidationException('protobuf conflicts with grpc, please remove grpc or protobuf extension');
        }
    }
}
