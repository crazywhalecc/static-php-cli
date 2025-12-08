<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\InitPackage;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Util\GlobalEnvManager;

#[Target('go-xcaddy')]
class go_xcaddy
{
    #[InitPackage]
    public function init(): void
    {
        if (is_dir(PKG_ROOT_PATH . '/go-xcaddy/bin')) {
            GlobalEnvManager::addPathIfNotExists(PKG_ROOT_PATH . '/go-xcaddy/bin');
            GlobalEnvManager::putenv('GOROOT=' . PKG_ROOT_PATH . '/go-xcaddy');
            GlobalEnvManager::putenv('GOBIN=' . PKG_ROOT_PATH . '/go-xcaddy/bin');
            GlobalEnvManager::putenv('GOPATH=' . PKG_ROOT_PATH . '/go-xcaddy/go');
        }
    }
}
