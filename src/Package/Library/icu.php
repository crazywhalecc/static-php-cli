<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SharedLibraryUtil;

#[Library('icu')]
class icu
{
    #[BeforeStage('icu', 'packPrebuilt')]
    public function beforePack(LibraryPackage $lib): void
    {
        FileSystem::replaceFileRegex("{$lib->getBinDir()}/icu-config", '/default_prefix=.*/m', 'default_prefix="{BUILD_ROOT_PATH}"');
    }

    #[BuildFor('Linux')]
    public function buildLinux(LibraryPackage $lib, ToolchainInterface $toolchain, PackageBuilder $builder): void
    {
        // runConfigureICU bakes CXXFLAGS/LDFLAGS, apply user flags too
        $userCxxFlags = trim((string) getenv('SPC_DEFAULT_CXXFLAGS'));
        $userLdFlags = trim((string) getenv('SPC_DEFAULT_LDFLAGS'));
        $cppflags = 'CPPFLAGS="-DU_CHARSET_IS_UTF8=1  -DU_USING_ICU_NAMESPACE=1 -DU_STATIC_IMPLEMENTATION=1 -DPIC -fPIC"';
        if (SharedLibraryUtil::buildsShared($lib)) {
            $cppflags = str_replace('-DU_STATIC_IMPLEMENTATION=1', '', $cppflags);
            $userLdFlags .= ' ' . SharedLibraryUtil::ldflags();
        }
        $cxxflags = "CXXFLAGS=\"-std=c++17 -DPIC -fPIC -fno-ident {$userCxxFlags}\"";
        $ldflags = $toolchain->isStatic() ? "LDFLAGS=\"-static {$userLdFlags}\"" : "LDFLAGS=\"{$userLdFlags}\"";
        shell()->cd($lib->getSourceDir() . '/source')->initializeEnv($lib)
            ->exec(
                "{$cppflags} {$cxxflags} {$ldflags} " .
                './runConfigureICU Linux ' .
                '--enable-static ' .
                (SharedLibraryUtil::buildsShared($lib) ? '--enable-shared ' : '--disable-shared ') .
                (SharedLibraryUtil::buildsShared($lib) ? '--with-data-packaging=library ' : '--with-data-packaging=static ') .
                '--enable-release=yes ' .
                '--enable-extras=no ' .
                '--enable-icuio=yes ' .
                '--enable-dyload=no ' .
                '--enable-tools=yes ' .
                '--enable-tests=no ' .
                '--enable-samples=no ' .
                '--prefix=' . $lib->getBuildRootPath()
            )
            ->exec('make clean')
            ->exec("make -j{$builder->concurrency}")
            ->exec('make install');

        $lib->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX);
        FileSystem::removeDir("{$lib->getLibDir()}/icu");
    }

    #[BuildFor('Darwin')]
    public function buildDarwin(LibraryPackage $lib, PackageBuilder $builder): void
    {
        shell()->cd($lib->getSourceDir() . '/source')
            ->exec("./runConfigureICU MacOSX --enable-static --disable-shared --disable-extras --disable-samples --disable-tests --prefix={$lib->getBuildRootPath()}")
            ->exec('make clean')
            ->exec("make -j{$builder->concurrency}")
            ->exec('make install');

        $lib->patchPkgconfPrefix(patch_option: PKGCONF_PATCH_PREFIX);
        FileSystem::removeDir("{$lib->getLibDir()}/icu");
    }
}
