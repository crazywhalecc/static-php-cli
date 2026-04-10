<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('gmagick')]
class gmagick extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--with-gmagick=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH;
    }

    public function patchBeforeBuildconf(): bool
    {
        // Remove the OpenMP check from config.m4 to avoid linking against libgomp
        // in static builds. The gmagick source code already has a fallback path
        // (usleep loop) when HAVE_OMP_PAUSE_RESOURCE_ALL is not defined.
        FileSystem::replaceFileRegex(
            SOURCE_PATH . '/php-src/ext/gmagick/config.m4',
            '/AC_MSG_CHECKING\(omp_pause_resource_all usability\).*?AC_MSG_RESULT\(no\)\n\t\t\]\)/s',
            'dnl OMP check removed for static build'
        );
        return true;
    }
}
