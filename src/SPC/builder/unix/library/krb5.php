<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixAutoconfExecutor;

trait krb5
{
    protected function build(): void
    {
        $this->source_dir .= '/src';
        shell()->cd($this->source_dir)->exec('autoreconf -if');
        UnixAutoconfExecutor::create($this)
            ->appendEnv([
                'LDFLAGS' => '-Wl,--allow-multiple-definition',
            ])
            ->optionalLib('ldap', '--with-ldap', '--without-ldap')
            ->optionalLib('libedit', '--with-readline', '--without-readline')
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
