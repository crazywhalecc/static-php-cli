<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('raphf')]
class raphf extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-raphf')]
    #[PatchDescription('Replace the aliases removed in PHP 8.6 and publish raphf headers into ext/raphf')]
    public function patchBeforeBuildconf(): void
    {
        foreach (glob("{$this->getBuildDir()}/src/*.[ch]") as $file) {
            FileSystem::replaceFileRegex($file, ['/\bZEND_RESULT_CODE\b/', '/\bzval_dtor\b/'], ['zend_result', 'zval_ptr_dtor_nogc']);
        }
        // ext/http includes "ext/raphf/php_raphf_api.h", but upstream only publishes that header out
        // of src/ from a Makefile fragment hooked to $(all_targets), which a per-SAPI `make cli`
        // never reaches. Publish it here, and drop the fragment's `clean` hook
        FileSystem::replaceFileStr("{$this->getBuildDir()}/Makefile.frag", "clean: raphf-clean-headers\n", '');
        foreach (glob("{$this->getBuildDir()}/src/*.h") as $header) {
            FileSystem::copy($header, "{$this->getBuildDir()}/" . basename($header));
        }
    }
}
