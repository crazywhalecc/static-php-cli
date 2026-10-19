<?php

declare(strict_types=1);

namespace Package\Library;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\PkgConfigUtil;
use StaticPHP\Util\SPCConfigUtil;

#[Library('postgresql')]
class postgresql extends LibraryPackage
{
    #[BeforeStage('php', [php::class, 'configureForUnix'], 'postgresql')]
    #[PatchDescription('Patch to avoid explicit_bzero detection issues on some systems')]
    public function patchBeforePHPConfigure(TargetPackage $package): void
    {
        if (SystemTarget::getTargetOS() === 'Darwin') {
            // on macOS, explicit_bzero is available but causes build failure due to detection issues, so we fake it as unavailable
            shell()->cd($package->getSourceDir())
                ->exec('sed -i.backup "s/ac_cv_func_explicit_bzero\" = xyes/ac_cv_func_explicit_bzero\" = x_fake_yes/" ./configure');
        }
    }

    #[PatchBeforeBuild]
    #[PatchDescription('Various patches before building PostgreSQL')]
    public function patchBeforeBuild(): bool
    {
        // These patches target the autoconf/Make build; the Windows build uses Meson (see buildWin).
        if (SystemTarget::getTargetOS() === 'Windows') {
            return true;
        }
        // skip the test on platforms where libpq infrastructure may be provided by statically-linked libraries
        FileSystem::replaceFileStr("{$this->getSourceDir()}/src/interfaces/libpq/Makefile", 'invokes exit\'; exit 1;', 'invokes exit\';');
        // disable shared libs build
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/src/Makefile.shlib",
            [
                '$(LINK.shared) -o $@ $(OBJS) $(LDFLAGS) $(LDFLAGS_SL) $(SHLIB_LINK)',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(pkglibdir)/$(shlib)\'',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(libdir)/$(shlib)\'',
                '$(INSTALL_SHLIB) $< \'$(DESTDIR)$(bindir)/$(shlib)\'',
            ],
            ''
        );
        return true;
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $src = $lib->getSourceDir();
        $build_root = $lib->getBuildRootPath();
        $lib_dir = $lib->getLibDir();
        $inc_dir = $lib->getIncludeDir();
        $build = "{$src}\\build";

        // Export the public pg_char_to_encoding()/pg_encoding_to_char() from libpgcommon.a so a
        // statically-linked libpq.a resolves them (PHP's ext/pgsql relies on them too). This mirrors
        // the Unix build's -UUSE_PRIVATE_ENCODING_FUNCS patch, but for the Meson build.
        FileSystem::replaceFileStr(
            "{$src}\\src\\common\\meson.build",
            "'c_args': ['-DUSE_PRIVATE_ENCODING_FUNCS'],",
            "'c_args': [],"
        );

        // Fresh Meson build dir (Meson refuses to reuse a dir configured differently).
        if (is_dir($build)) {
            FileSystem::removeDir($build);
        }

        // Meson's OpenSSL detection link-tests CRYPTO_new_ex_data; our static libcrypto needs its
        // Win32 deps (and zlib, since OpenSSL was built with zlib) on the link line to succeed.
        $ld = 'ws2_32.lib gdi32.lib advapi32.lib crypt32.lib user32.lib secur32.lib zlibstatic.lib';

        $configure = 'meson setup build'
            . ' --prefix=' . escapeshellarg($build_root)
            . ' -Ddefault_library=static'   // static libpq.a / libpgcommon.a / libpgport.a
            . ' -Db_vscrt=mt'               // /MT static CRT, matching the rest of the build
            . ' -Dssl=openssl'
            // Everything libpq doesn't need: keeps deps minimal and avoids server-only detection.
            . ' -Dzlib=disabled -Dnls=disabled -Dreadline=disabled -Dicu=disabled'
            . ' -Dlz4=disabled -Dzstd=disabled -Dtap_tests=disabled'
            . ' -Dplperl=disabled -Dplpython=disabled -Dpltcl=disabled'
            . ' -Dgssapi=disabled -Dldap=disabled -Dlibxml=disabled -Dlibxslt=disabled'
            . ' -Dextra_include_dirs=' . escapeshellarg("{$build_root}\\include")
            . ' -Dextra_lib_dirs=' . escapeshellarg($lib_dir);

        // Build only the three frontend static libs (not the server) — keeps it fast and avoids
        // needing every backend dependency. meson/ninja/win_bison/win_flex/perl come from the
        // tool packages declared in the package config (tools@windows).
        $targets = 'src/interfaces/libpq/libpq.a src/common/libpgcommon.a src/port/libpgport.a';

        cmd()->cd($src)
            ->setEnv([
                'LIB' => $lib_dir . ';' . (getenv('LIB') ?: ''),
                'LDFLAGS' => $ld,
            ])
            ->exec($configure)
            ->exec("ninja -C build {$targets}");

        // Install the static libs under the names PHP's ext/pgsql + frankenphp expect (.lib).
        FileSystem::createDir($lib_dir);
        FileSystem::createDir($inc_dir);
        FileSystem::copy("{$build}\\src\\interfaces\\libpq\\libpq.a", "{$lib_dir}\\libpq.lib");
        FileSystem::copy("{$build}\\src\\common\\libpgcommon.a", "{$lib_dir}\\libpgcommon.lib");
        FileSystem::copy("{$build}\\src\\port\\libpgport.a", "{$lib_dir}\\libpgport.lib");

        // Install the public libpq headers (PG18 no longer ships pg_config_ext.h).
        FileSystem::copy("{$src}\\src\\interfaces\\libpq\\libpq-fe.h", "{$inc_dir}\\libpq-fe.h");
        FileSystem::copy("{$src}\\src\\include\\postgres_ext.h", "{$inc_dir}\\postgres_ext.h");
        FileSystem::createDir("{$inc_dir}\\libpq");
        FileSystem::copy("{$src}\\src\\include\\libpq\\libpq-fs.h", "{$inc_dir}\\libpq\\libpq-fs.h");
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(PackageInstaller $installer, PackageBuilder $builder): void
    {
        $spc_config = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc_config->getPackageDepsConfig('postgresql', array_keys($installer->getResolvedPackages()));

        $env_vars = [
            'CFLAGS' => $config['cflags'] . ' -std=c17',
            'CPPFLAGS' => '-DPIC',
            'LDFLAGS' => $config['ldflags'],
            'LIBS' => $config['libs'],
        ];

        if ($ldLibraryPath = getenv('SPC_LD_LIBRARY_PATH')) {
            $env_vars['LD_LIBRARY_PATH'] = $ldLibraryPath;
        }

        FileSystem::resetDir("{$this->getSourceDir()}/build");

        if ($installer->isPackageResolved('ldap')) {
            $ldap_libs = clean_spaces(implode(' ', PkgConfigUtil::getLibsArray('ldap')));
            FileSystem::replaceFileStr("{$this->getSourceDir()}/configure", '-lldap', $ldap_libs);
        }

        // PHP source relies on the non-private encoding functions in libpgcommon.a
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/src/common/Makefile",
            '$(OBJS_FRONTEND): CPPFLAGS += -DUSE_PRIVATE_ENCODING_FUNCS',
            '$(OBJS_FRONTEND): CPPFLAGS += -UUSE_PRIVATE_ENCODING_FUNCS -DFRONTEND',
        );

        // configure
        $shell = shell()->cd("{$this->getSourceDir()}/build")->initializeEnv($this)
            ->appendEnv($env_vars)
            ->exec(
                '../configure ' .
                "--prefix={$this->getBuildRootPath()} " .
                '--enable-coverage=no ' .
                '--with-ssl=openssl ' .
                '--with-readline ' .
                '--with-libxml ' .
                ($installer->isPackageResolved('icu') ? '--with-icu ' : '--without-icu ') .
                ($installer->isPackageResolved('ldap') ? '--with-ldap ' : '--without-ldap ') .
                ($installer->isPackageResolved('libxslt') ? '--with-libxslt ' : '--without-libxslt ') .
                ($installer->isPackageResolved('zstd') ? '--with-zstd ' : '--without-zstd ') .
                '--without-lz4 ' .
                '--without-perl ' .
                '--without-python ' .
                '--without-pam ' .
                '--without-bonjour ' .
                '--without-tcl '
            );

        // patch ldap lib
        if ($installer->isPackageResolved('ldap')) {
            $libs = PkgConfigUtil::getLibsArray('ldap');
            $libs = clean_spaces(implode(' ', $libs));
            FileSystem::replaceFileStr("{$this->getSourceDir()}/build/config.status", '-lldap', $libs);
            FileSystem::replaceFileStr("{$this->getSourceDir()}/build/src/Makefile.global", '-lldap', $libs);
        }

        $shell
            ->exec('make -C src/bin/pg_config install')
            ->exec('make -C src/include install')
            ->exec('make -C src/common install')
            ->exec('make -C src/port install')
            ->exec('make -C src/interfaces/libpq install');

        // remove dynamic libs
        shell()->cd($this->getSourceDir() . '/build')
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.so*")
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.dylib");

        FileSystem::replaceFileStr("{$this->getLibDir()}/pkgconfig/libpq.pc", '-lldap', '-lldap -llber');
    }
}
