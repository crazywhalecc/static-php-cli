<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\LinuxUtil;
use StaticPHP\Util\System\WindowsUtil;

#[Library('openssl')]
class openssl
{
    #[Validate]
    public function validate(): void
    {
        if (SystemTarget::getTargetOS() === 'Windows') {
            global $argv;
            $perl_path_native = PKG_ROOT_PATH . '\strawberry-perl-' . arch2gnu(php_uname('m')) . '-win\perl\bin\perl.exe';
            $perl = file_exists($perl_path_native) ? ($perl_path_native) : WindowsUtil::findCommand('perl.exe');
            if ($perl === null) {
                throw new EnvironmentException(
                    'You need to install perl first!',
                    "Please run \"{$argv[0]} doctor\" to fix the environment.",
                );
            }
            ApplicationContext::set('perl', $perl);
        }
    }

    #[BuildFor('Windows')]
    public function buildWin(LibraryPackage $lib): void
    {
        $perl = ApplicationContext::get('perl');
        $cmd = cmd()->cd($lib->getSourceDir())
            ->exec(
                "{$perl} Configure zlib VC-WIN64A " .
                'no-shared ' .
                '--prefix=' . quote($lib->getBuildRootPath()) . ' ' .
                '--with-zlib-lib=' . quote($lib->getLibDir()) . ' ' .
                '--with-zlib-include=' . quote($lib->getIncludeDir()) . ' ' .
                '--release ' .
                'no-legacy '
            );

        // patch zlib
        FileSystem::replaceFileStr("{$lib->getSourceDir()}\\Makefile", 'ZLIB1', 'zlibstatic.lib');
        // patch debug: https://stackoverflow.com/questions/18486243/how-do-i-build-openssl-statically-linked-against-windows-runtime
        FileSystem::replaceFileStr("{$lib->getSourceDir()}\\Makefile", '/debug', '/incremental:no /opt:icf /dynamicbase /nxcompat /ltcg /nodefaultlib:msvcrt');

        // build
        $cmd->exec("nmake install_dev CNF_LDFLAGS=\"/NODEFAULTLIB:kernel32.lib /NODEFAULTLIB:msvcrt /NODEFAULTLIB:msvcrtd /DEFAULTLIB:libcmt /LIBPATH:{$lib->getLibDir()} zlibstatic.lib\"");

        // copy necessary c files
        FileSystem::copy("{$lib->getSourceDir()}\\ms\\applink.c", "{$lib->getIncludeDir()}\\openssl\\applink.c");

        // patch cmake outputs
        FileSystem::replaceFileRegex(
            "{$lib->getLibDir()}\\cmake\\OpenSSL\\OpenSSLConfig.cmake",
            '/set\(OPENSSL_LIBCRYPTO_DEPENDENCIES .*\)/m',
            'set(OPENSSL_LIBCRYPTO_DEPENDENCIES "${OPENSSL_LIBRARY_DIR}" ws2_32.lib gdi32.lib advapi32.lib crypt32.lib user32.lib)'
        );
    }

    #[BuildFor('Darwin')]
    public function buildForDarwin(LibraryPackage $pkg): void
    {
        $zlib_libs = $pkg->getInstaller()->getLibraryPackage('zlib')->getStaticLibFiles();
        $arch = getenv('SPC_ARCH');

        shell()->cd($pkg->getSourceDir())->initializeEnv($pkg)
            ->exec(
                './Configure no-shared zlib ' .
                "--prefix={$pkg->getBuildRootPath()} " .
                '--libdir=lib ' .
                '--openssldir=/etc/ssl ' .
                "darwin64-{$arch}-cc"
            )
            ->exec('make clean')
            ->exec("make -j{$pkg->getBuilder()->concurrency} CNF_EX_LIBS=\"{$zlib_libs}\"")
            ->exec('make install_sw');
        $this->patchPkgConfig($pkg);
    }

    #[BuildFor('Linux')]
    public function build(LibraryPackage $lib): void
    {
        $arch = getenv('SPC_ARCH');

        $env = "CC='" . getenv('CC') . ' -idirafter ' . BUILD_INCLUDE_PATH .
            ' -idirafter /usr/include/ ' .
            ' -idirafter /usr/include/' . getenv('SPC_ARCH') . '-linux-gnu/ ' .
            "' ";

        $ex_lib = trim($lib->getInstaller()->getLibraryPackage('zlib')->getStaticLibFiles()) . ' -ldl -pthread';
        $zlib_extra =
            '--with-zlib-include=' . BUILD_INCLUDE_PATH . ' ' .
            '--with-zlib-lib=' . BUILD_LIB_PATH . ' ';

        $openssl_dir = getenv('OPENSSLDIR') ?: null;
        $openssl_dir ??= LinuxUtil::getOSRelease()['dist'] === 'redhat' ? '/etc/pki/tls' : '/etc/ssl';
        $ex_lib = trim($ex_lib);

        shell()->cd($lib->getSourceDir())->initializeEnv($lib)
            ->exec(
                "{$env} ./Configure no-shared zlib " .
                "--prefix={$lib->getBuildRootPath()} " .
                "--libdir={$lib->getLibDir()} " .
                "--openssldir={$openssl_dir} " .
                "{$zlib_extra}" .
                'enable-pie ' .
                'no-legacy ' .
                'no-tests ' .
                "linux-{$arch}"
            )
            ->exec('make clean')
            ->exec("make -j{$lib->getBuilder()->concurrency} CNF_EX_LIBS=\"{$ex_lib}\"")
            ->exec('make install_sw');
        $this->patchPkgConfig($lib);
    }

    private function patchPkgConfig(LibraryPackage $pkg): void
    {
        $pkg->patchPkgconfPrefix(['libssl.pc', 'openssl.pc', 'libcrypto.pc']);
        // patch for openssl 3.3.0+
        if (!str_contains($file = FileSystem::readFile("{$pkg->getLibDir()}/pkgconfig/libssl.pc"), 'prefix=')) {
            FileSystem::writeFile("{$pkg->getLibDir()}/pkgconfig/libssl.pc", "prefix={$pkg->getBuildRootPath()}\n{$file}");
        }
        if (!str_contains($file = FileSystem::readFile("{$pkg->getLibDir()}/pkgconfig/openssl.pc"), 'prefix=')) {
            FileSystem::writeFile("{$pkg->getLibDir()}/pkgconfig/openssl.pc", "prefix={$pkg->getBuildRootPath()}\n{$file}");
        }
        if (!str_contains($file = FileSystem::readFile("{$pkg->getLibDir()}/pkgconfig/libcrypto.pc"), 'prefix=')) {
            FileSystem::writeFile("{$pkg->getLibDir()}/pkgconfig/libcrypto.pc", "prefix={$pkg->getBuildRootPath()}\n{$file}");
        }
        FileSystem::replaceFileRegex("{$pkg->getLibDir()}/pkgconfig/libcrypto.pc", '/Libs.private:.*/m', 'Requires.private: zlib');
        FileSystem::replaceFileRegex("{$pkg->getLibDir()}/cmake/OpenSSL/OpenSSLConfig.cmake", '/set\(OPENSSL_LIBCRYPTO_DEPENDENCIES .*\)/m', 'set(OPENSSL_LIBCRYPTO_DEPENDENCIES "${OPENSSL_LIBRARY_DIR}/libz.a")');
    }
}
