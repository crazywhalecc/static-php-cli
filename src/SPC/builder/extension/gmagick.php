<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('gmagick')]
class gmagick extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // PHP 8.5 removed zend_exception_get_default(), use zend_ce_exception instead
        FileSystem::replaceFileStr($this->source_dir . '/gmagick.c', 'zend_exception_get_default()', 'zend_ce_exception');

        // Remove the entire OpenMP check block from config.m4 to avoid linking
        // against libgomp in static builds. gmagick's config.m4 uses PHP_CHECK_FUNC
        // which does not honour ac_cv cache variables, so we must patch the source.
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/ext/gmagick/config.m4',
            '/AC_MSG_CHECKING\(omp_pause_resource_all usability\).*?AC_MSG_RESULT\(no\)\n\t\t\]\)/s',
            'dnl OMP check removed for static build'
        );
        return true;
    }

    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-gmagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH;
    }
}
