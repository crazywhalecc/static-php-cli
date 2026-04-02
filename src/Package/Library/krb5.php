<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SPCConfigUtil;

#[Library('krb5')]
class krb5
{
    #[BuildFor('Linux')]
    #[BuildFor('Darwin')]
    public function build(LibraryPackage $lib, PackageInstaller $installer): void
    {
        shell()->cd($lib->getSourceRoot())->exec('autoreconf -if');

        $resolved = array_keys($installer->getResolvedPackages());
        $spc = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc->getPackageDepsConfig($lib->getName(), $resolved, include_suggests: true);
        $extraEnv = [
            'CFLAGS' => '-fcommon',
            'LIBS' => $config['libs'],
        ];
        if (getenv('SPC_LD_LIBRARY_PATH') && getenv('SPC_LIBRARY_PATH')) {
            $extraEnv = [...$extraEnv, ...[
                'LD_LIBRARY_PATH' => getenv('SPC_LD_LIBRARY_PATH'),
                'LIBRARY_PATH' => getenv('SPC_LIBRARY_PATH'),
            ]];
        }
        $args = [
            '--disable-nls',
            '--disable-rpath',
            '--without-system-verto',
        ];
        if (SystemTarget::getTargetOS() === 'Darwin') {
            $extraEnv['LDFLAGS'] = '-framework Kerberos';
            $args[] = 'ac_cv_func_secure_getenv=no';
        }
        UnixAutoconfExecutor::create($lib)
            ->appendEnv($extraEnv)
            ->optionalPackage('ldap', '--with-ldap', '--without-ldap')
            ->optionalPackage('libedit', '--with-libedit', '--without-libedit')
            ->configure(...$args)
            ->make();
        $lib->patchPkgconfPrefix([
            'krb5-gssapi.pc',
            'krb5.pc',
            'kadm-server.pc',
            'kadm-client.pc',
            'kdb.pc',
            'mit-krb5-gssapi.pc',
            'mit-krb5.pc',
            'gssrpc.pc',
        ]);
        // libkrb5support is in Libs.private of mit-krb5.pc, but CMake's pkg_check_modules
        // does not follow Libs.private for static linking. Promote it to Libs so that
        // consumers linking static binaries (e.g. the curl exe) can resolve _k5_* symbols.
        $mit_krb5_pc = BUILD_ROOT_PATH . '/lib/pkgconfig/mit-krb5.pc';
        FileSystem::replaceFileStr($mit_krb5_pc, 'Libs.private: -lkrb5support', 'Libs.private:');
        FileSystem::replaceFileStr($mit_krb5_pc, '-lcom_err', '-lcom_err -lkrb5support');
    }
}
