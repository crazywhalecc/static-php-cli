<?php

declare(strict_types=1);

namespace Package\Library;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\PatchBeforeBuild;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\FileSystemException;
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
        // fix aarch64 build on glibc 2.17 (e.g. CentOS 7)
        if (SystemTarget::getLibcVersion() === '2.17' && SystemTarget::getTargetArch() === 'aarch64') {
            try {
                FileSystem::replaceFileStr("{$this->getSourceDir()}/src/port/pg_popcount_aarch64.c", 'HWCAP_SVE', '0');
                FileSystem::replaceFileStr(
                    "{$this->getSourceDir()}/src/port/pg_crc32c_armv8_choose.c",
                    '#if defined(__linux__) && !defined(__aarch64__) && !defined(HWCAP2_CRC32)',
                    '#if defined(__linux__) && !defined(HWCAP_CRC32)'
                );
            } catch (FileSystemException) {
                // allow file not-existence to make it compatible with old and new version
            }
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

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function buildUnix(PackageInstaller $installer, PackageBuilder $builder): void
    {
        $spc_config = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc_config->getPackageDepsConfig('postgresql', array_keys($installer->getResolvedPackages()), include_suggests: $builder->getOption('with-suggests', false));

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
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.so.*")
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.so")
            ->exec("rm -rf {$this->getBuildRootPath()}/lib/*.dylib");

        FileSystem::replaceFileStr("{$this->getLibDir()}/pkgconfig/libpq.pc", '-lldap', '-lldap -llber');
    }
}
