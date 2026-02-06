<?php

declare(strict_types=1);

namespace Package\Artifact;

use Package\Target\php;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SourcePatcher;

class php_src
{
    #[AfterSourceExtract('php-src')]
    #[PatchDescription('Patch PHP source for libxml2 2.12 compatibility on Alpine Linux')]
    public function patchPhpLibxml212(): void
    {
        $ver_id = php::getPHPVersionID(return_null_if_failed: true);
        if ($ver_id) {
            if ($ver_id < 80000) {
                SourcePatcher::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
                return;
            }
            if ($ver_id < 80100) {
                SourcePatcher::patchFile('spc_fix_libxml2_12_php80.patch', SOURCE_PATH . '/php-src');
                SourcePatcher::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
                return;
            }
            if ($ver_id < 80200) {
                // self::patchFile('spc_fix_libxml2_12_php81.patch', SOURCE_PATH . '/php-src');
                SourcePatcher::patchFile('spc_fix_alpine_build_php80.patch', SOURCE_PATH . '/php-src');
            }
        }
    }

    #[AfterSourceExtract('php-src')]
    #[PatchDescription('Patch GD extension for Windows builds')]
    public function patchGDWin32(): void
    {
        $ver_id = php::getPHPVersionID(return_null_if_failed: true);
        if ($ver_id) {
            if ($ver_id < 80200) {
                // see: https://github.com/php/php-src/commit/243966177e39eb71822935042c3f13fa6c5b9eed
                FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/gd/libgd/gdft.c', '#ifndef MSWIN32', '#ifndef _WIN32');
            }
            // custom config.w32, because official config.w32 is hard-coded many things
            $origin = $ver_id >= 80100 ? file_get_contents(ROOT_DIR . '/src/globals/extra/gd_config_81.w32') : file_get_contents(ROOT_DIR . '/src/globals/extra/gd_config_80.w32');
            file_put_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32.bak', file_get_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32'));
            file_put_contents(SOURCE_PATH . '/php-src/ext/gd/config.w32', $origin);
        }
    }

    #[AfterSourceExtract('php-src')]
    #[PatchDescription('Patch FFI extension on CentOS 7 with -O3 optimization (strncmp issue)')]
    public function patchFfiCentos7FixO3strncmp(): void
    {
        spc_skip_if(!($ver = SystemTarget::getLibcVersion()) || version_compare($ver, '2.17', '>'));
        $ver_id = php::getPHPVersionID(return_null_if_failed: true);
        spc_skip_if($ver_id === null || $ver_id < 80316);
        SourcePatcher::patchFile('ffi_centos7_fix_O3_strncmp.patch', SOURCE_PATH . '/php-src');
    }

    #[AfterSourceExtract('php-src')]
    #[PatchDescription('Add LICENSE file to IMAP extension if missing')]
    public function patchImapLicense(): void
    {
        if (!file_exists(SOURCE_PATH . '/php-src/ext/imap/LICENSE') && is_dir(SOURCE_PATH . '/php-src/ext/imap')) {
            file_put_contents(SOURCE_PATH . '/php-src/ext/imap/LICENSE', file_get_contents(ROOT_DIR . '/src/globals/extra/Apache_LICENSE'));
        }
    }
}
