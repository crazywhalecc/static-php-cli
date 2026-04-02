<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Util\FileSystem;

#[Library('idn2')]
class idn2
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib): void
    {
        UnixAutoconfExecutor::create($lib)
            ->configure(
                '--disable-nls',
                '--disable-doc',
                '--enable-year2038',
                '--disable-rpath'
            )
            ->optionalPackage('libiconv', '--with-libiconv-prefix=' . BUILD_ROOT_PATH)
            ->optionalPackage('libunistring', '--with-libunistring-prefix=' . BUILD_ROOT_PATH)
            ->optionalPackage('gettext', '--with-libnintl-prefix=' . BUILD_ROOT_PATH)
            ->make();
        $lib->patchPkgconfPrefix(['libidn2.pc']);
        $lib->patchLaDependencyPrefix();
        // libunistring is in Libs.private of libidn2.pc, but CMake's pkg_check_modules
        // does not follow Libs.private for static linking. Promote it to Libs so that
        // consumers linking static binaries (e.g. the curl exe) can resolve _uc_* / _u32_* symbols.
        $libidn2_pc = BUILD_ROOT_PATH . '/lib/pkgconfig/libidn2.pc';
        FileSystem::replaceFileStr($libidn2_pc, 'Libs: -L${libdir} -lidn2', 'Libs: -L${libdir} -lidn2 -lunistring');
    }
}
