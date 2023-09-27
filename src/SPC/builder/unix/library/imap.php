<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

trait imap
{
    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function build(): void
    {
        //        ext-imap is NOT thread safe, do not build with ZTS builds
        //        if ($this->builder->getOption('enable-zts')) {
        //            return;
        //        }
        if ($this->builder->getLib('openssl') !== null) {
            FileSystem::replaceFileStr(
                $this->source_dir . '/Makefile',
                'SSLINCLUDE=/usr/include/openssl',
                'SSLINCLUDE=' . BUILD_INCLUDE_PATH
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/Makefile',
                'SSLLIB=/usr/lib',
                'SSLLIB=' . BUILD_LIB_PATH
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/Makefile',
                '"-DMAC_OSX_KLUDGE=1',
                ''
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/osdep/unix/Makefile',
                '-lcrypto -lz -lpam',
                '-lcrypto'
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/osdep/unix/Makefile',
                '-lcrypto',
                '-lcrypto -lz -lpam'
            );
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/osdep/unix/ssl_unix.c',
                "#include <x509v3.h>\n#include <ssl.h>",
                "#include <ssl.h>\n#include <x509v3.h>"
            );
            // https://salsa.debian.org/holmgren/uw-imap/raw/master/debian/patches/2014_openssl1.1.1_sni.patch
            // this was not applied in the github source yet
            FileSystem::replaceFileStr(
                $this->source_dir . '/src/osdep/unix/ssl_unix.c',
                <<<'EOL'
if (!(stream->con = (SSL *) SSL_new (stream->context)))
    return "SSL connection failed";
  bio = BIO_new_socket (stream->tcpstream->tcpsi,BIO_NOCLOSE);
EOL,
                <<<'EOL'
if (!(stream->con = (SSL *) SSL_new (stream->context)))
    return "SSL connection failed";
#if OPENSSL_VERSION_NUMBER >= 0x10101000
  /* Use SNI in case server requires it with TLSv1.3.
   * Literal IP addresses not permitted per RFC 6066. */
  if (!a2i_IPADDRESS(host)) {
    ERR_clear_error();
    SSL_set_tlsext_host_name(stream->con,host);
  }
#endif
  bio = BIO_new_socket (stream->tcpstream->tcpsi,BIO_NOCLOSE);"
EOL
            );
        }
        $platform = 'lr5';
        shell()->cd($this->source_dir)
            ->exec('touch ip6')
            ->exec(
                "{$this->builder->configure_env} make {$platform} " .
                'EXTRACFLAGS="-fPIC" ' .
                ($this->builder->getLib('openssl') === null ? ' SSLTYPE=none' : 'SPECIALAUTHENTICATORS=ssl SSLTYPE=unix')
            );
        shell()
            ->exec("cp -rf {$this->source_dir}/c-client/c-client.a " . BUILD_LIB_PATH . '/libc-client.a')
            ->exec("cp -rf {$this->source_dir}/c-client/*.c " . BUILD_LIB_PATH . '/')
            ->exec("cp -rf {$this->source_dir}/c-client/*.h " . BUILD_INCLUDE_PATH . '/')
            ->exec("cp -rf {$this->source_dir}/src/osdep/unix/*.h " . BUILD_INCLUDE_PATH . '/');
    }
}
