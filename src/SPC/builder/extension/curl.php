<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('curl')]
class curl extends Extension
{
    /**
     * @throws FileSystemException
     */
    public function patchBeforeBuildconf(): bool
    {
        logger()->info('patching before-configure for curl checks');
        $file1 = "AC_DEFUN([PHP_CHECK_LIBRARY], [\n  $3\n])";
        $files = FileSystem::readFile(SOURCE_PATH . '/php-src/ext/curl/config.m4');
        $file2 = 'AC_DEFUN([PHP_CHECK_LIBRARY], [
  save_old_LDFLAGS=$LDFLAGS
  ac_stuff="$5"

  save_ext_shared=$ext_shared
  ext_shared=yes
  PHP_EVAL_LIBLINE([$]ac_stuff, LDFLAGS)
  AC_CHECK_LIB([$1],[$2],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    $3
  ],[
    LDFLAGS=$save_old_LDFLAGS
    ext_shared=$save_ext_shared
    unset ac_cv_lib_$1[]_$2
    $4
  ])dnl
])';
        file_put_contents(SOURCE_PATH . '/php-src/ext/curl/config.m4', $file1 . "\n" . $files . "\n" . $file2);
        return true;
    }

    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function patchBeforeConfigure(): bool
    {
        $frameworks = $this->builder instanceof MacOSBuilder ? ' ' . $this->builder->getFrameworks(true) . ' ' : '';
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/-lcurl/', $this->getLibFilesString() . $frameworks);
        return true;
    }
}
