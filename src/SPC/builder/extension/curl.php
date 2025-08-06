<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\PatchException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('curl')]
class curl extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        logger()->info('patching before-configure for curl checks');
        $file1 = "AC_DEFUN([PHP_CHECK_LIBRARY], [\n  $3\n])";
        $files = FileSystem::readFile($this->source_dir . '/config.m4');
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
        file_put_contents($this->source_dir . '/config.m4', $file1 . "\n" . $files . "\n" . $file2);
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        $frameworks = $this->builder instanceof MacOSBuilder ? ' ' . $this->builder->getFrameworks(true) . ' ' : '';
        FileSystem::replaceFileRegex(SOURCE_PATH . '/php-src/configure', '/-lcurl/', $this->getLibFilesString() . $frameworks);
        $this->patchBeforeSharedConfigure();
        return true;
    }

    public function patchBeforeMake(): bool
    {
        $patched = parent::patchBeforeMake();
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
        if ($this->builder instanceof WindowsBuilder && !str_contains($extra_libs, 'secur32.lib')) {
            $extra_libs .= ' secur32.lib';
            putenv('SPC_EXTRA_LIBS=' . trim($extra_libs));
            return true;
        }
        return $patched;
    }

    public function patchBeforeSharedConfigure(): bool
    {
        $file = $this->source_dir . '/config.m4';
        $content = FileSystem::readFile($file);

        // Inject patch before it
        $patch = ' save_LIBS="$LIBS"
  LIBS="$LIBS $CURL_LIBS"
';
        // Check if already patched
        if (str_contains($content, $patch)) {
            return false; // Already patched
        }

        // Match the line containing PHP_CHECK_LIBRARY for curl
        $pattern = '/(PHP_CHECK_LIBRARY\(\[curl],\s*\[curl_easy_perform],)/';

        // Restore LIBS after the check — append this just after the macro block
        $restore = '
  LIBS="$save_LIBS"';

        // Apply patch
        $patched = preg_replace_callback($pattern, function ($matches) use ($patch) {
            return $patch . $matches[1];
        }, $content, 1);

        // Inject restore after the matching PHP_CHECK_LIBRARY block
        $patched = preg_replace(
            '/(PHP_CHECK_LIBRARY\(\[curl],\s*\[curl_easy_perform],.*?\)\n)/s',
            "$1{$restore}\n",
            $patched,
            1
        );

        if ($patched === null) {
            throw new PatchException('shared extension curl patcher', 'Failed to patch config.m4 due to a regex error');
        }

        FileSystem::writeFile($file, $patched);
        return true;
    }

    public function buildUnixShared(): void
    {
        if (!$this->builder instanceof LinuxBuilder) {
            parent::buildUnixShared();
            return;
        }

        FileSystem::replaceFileStr(
            $this->source_dir . '/config.m4',
            ['$ext_dir/phar.1', '$ext_dir/phar.phar.1'],
            ['${ext_dir}phar.1', '${ext_dir}phar.phar.1']
        );
        try {
            parent::buildUnixShared();
        } finally {
            FileSystem::replaceFileStr(
                $this->source_dir . '/config.m4',
                ['${ext_dir}phar.1', '${ext_dir}phar.phar.1'],
                ['$ext_dir/phar.1', '$ext_dir/phar.phar.1']
            );
        }
    }
}
