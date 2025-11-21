<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCConfigUtil;

trait krb5
{
    protected function build(): void
    {
        $origin_source_dir = $this->source_dir;
        $this->source_dir .= '/src';
        shell()->cd($this->source_dir)->exec('autoreconf -if');
        $libs = array_map(fn ($x) => $x->getName(), $this->getDependencies(true));
        $spc = new SPCConfigUtil($this->builder, ['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc->config(libraries: $libs, include_suggest_lib: $this->builder->getOption('with-suggested-libs', false));
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
        if (PHP_OS_FAMILY === 'Darwin') {
            $extraEnv['LDFLAGS'] = '-framework Kerberos';
            $args[] = 'ac_cv_func_secure_getenv=no';
        }
        UnixAutoconfExecutor::create($this)
            ->appendEnv($extraEnv)
            ->optionalLib('ldap', '--with-ldap', '--without-ldap')
            ->optionalLib('libedit', '--with-libedit', '--without-libedit')
            ->configure(...$args)
            ->make();
        $this->patchPkgconfPrefix([
            'krb5-gssapi.pc',
            'krb5.pc',
            'kadm-server.pc',
            'kadm-client.pc',
            'kdb.pc',
            'mit-krb5-gssapi.pc',
            'mit-krb5.pc',
            'gssrpc.pc',
        ]);
        $this->source_dir = $origin_source_dir;
    }
}
