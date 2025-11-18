<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;
use SPC\util\SPCConfigUtil;

trait krb5
{
    protected function build(): void
    {
        $this->source_dir .= '/src';
        shell()->cd($this->source_dir)->exec('autoreconf -if');
        $libs = array_map(fn ($x) => $x->getName(), $this->getDependencies(true));
        $spc = new SPCConfigUtil($this->builder, ['no_php' => true, 'libs_only_deps' => true]);
        $config = $spc->config(libraries: $libs, include_suggest_lib: $this->builder->getOption('with-suggested-libs', false));
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'LIBS' => $config['libs'],
                'LDFLAGS' => '-Wl,--allow-multiple-definition',
            ])
            ->optionalLib('ldap', '--with-ldap', '--without-ldap')
            ->optionalLib('libedit', '--with-libedit', '--without-libedit')
            ->configure(
                '--disable-nls',
                '--disable-rpath',
            )
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
    }
}
