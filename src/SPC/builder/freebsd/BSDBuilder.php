<?php

declare(strict_types=1);

namespace SPC\builder\freebsd;

use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

class BSDBuilder extends UnixBuilderBase
{
    /** @var bool Micro patch phar flag */
    private bool $phar_patched = false;

    /**
     * @throws RuntimeException
     * @throws WrongUsageException
     * @throws FileSystemException
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;

        // ---------- set necessary options ----------
        // set C Compiler (default: clang)
        f_putenv('CC=' . $this->getOption('cc', 'clang'));
        // set C++ Compiler (default: clang++)
        f_putenv('CXX=' . $this->getOption('cxx', 'clang++'));
        // set PATH
        f_putenv('PATH=' . BUILD_ROOT_PATH . '/bin:' . getenv('PATH'));
        // set PKG_CONFIG
        f_putenv('PKG_CONFIG=' . BUILD_ROOT_PATH . '/bin/pkg-config');
        // set PKG_CONFIG_PATH
        f_putenv('PKG_CONFIG_PATH=' . BUILD_LIB_PATH . '/pkgconfig/');

        // set arch (default: current)
        $this->setOptionIfNotExist('arch', php_uname('m'));
        $this->setOptionIfNotExist('gnu-arch', arch2gnu($this->getOption('arch')));

        // ---------- set necessary compile environments ----------
        // concurrency
        $this->concurrency = SystemUtil::getCpuCount();
        // cflags
        $this->arch_c_flags = SystemUtil::getArchCFlags($this->getOption('arch'));
        $this->arch_cxx_flags = SystemUtil::getArchCFlags($this->getOption('arch'));
        // cmake toolchain
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile('BSD', $this->getOption('arch'), $this->arch_c_flags);

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * Just start to build statically linked php binary
     *
     * @param  int                 $build_target build target
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public function buildPHP(int $build_target = BUILD_TARGET_NONE): void
    {
        // ---------- Update extra-libs ----------
        $extra_libs = $this->getOption('extra-libs', '');
        // add libc++, some extensions or libraries need it (C++ cannot be linked statically)
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . ($this->hasCpp() ? '-lc++ ' : '');
        if (!$this->getOption('bloat', false)) {
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', $this->getAllStaticLibFiles());
        } else {
            logger()->info('bloat linking');
            $extra_libs .= (empty($extra_libs) ? '' : ' ') . implode(' ', array_map(fn ($x) => "-Wl,-force_load,{$x}", array_filter($this->getAllStaticLibFiles())));
        }
        $this->setOption('extra-libs', $extra_libs);

        $this->emitPatchPoint('before-php-buildconf');
        SourcePatcher::patchBeforeBuildconf($this);

        shell()->cd(SOURCE_PATH . '/php-src')->exec('./buildconf --force');

        $this->emitPatchPoint('before-php-configure');
        SourcePatcher::patchBeforeConfigure($this);

        $json_74 = $this->getPHPVersionID() < 80000 ? '--enable-json ' : '';
        $zts_enable = $this->getPHPVersionID() < 80000 ? '--enable-maintainer-zts --disable-zend-signals' : '--enable-zts --disable-zend-signals';
        $zts = $this->getOption('enable-zts', false) ? $zts_enable : '';

        $config_file_path = $this->getOption('with-config-file-path', false) ?
            ('--with-config-file-path=' . $this->getOption('with-config-file-path') . ' ') : '';
        $config_file_scan_dir = $this->getOption('with-config-file-scan-dir', false) ?
            ('--with-config-file-scan-dir=' . $this->getOption('with-config-file-scan-dir') . ' ') : '';

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .     // Not detect memory leak
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "CFLAGS='{$this->arch_c_flags} -Werror=unknown-warning-option' " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' : '--disable-fpm ') .
                ($enableEmbed ? '--enable-embed=static ' : '--disable-embed ') .
                ($enableMicro ? '--enable-micro ' : '--disable-micro ') .
                $config_file_path .
                $config_file_scan_dir .
                $json_74 .
                $zts .
                $this->makeExtensionArgs()
            );

        $this->emitPatchPoint('before-php-make');
        SourcePatcher::patchBeforeMake($this);

        $this->cleanMake();

        if ($enableCli) {
            logger()->info('building cli');
            $this->buildCli();
        }
        if ($enableFpm) {
            logger()->info('building fpm');
            $this->buildFpm();
        }
        if ($enableMicro) {
            logger()->info('building micro');
            $this->buildMicro();
        }
        if ($enableEmbed) {
            logger()->info('building embed');
            if ($enableMicro) {
                FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/Makefile', 'OVERALL_TARGET =', 'OVERALL_TARGET = libphp.la');
            }
            $this->buildEmbed();
        }

        if (php_uname('m') === $this->getOption('arch')) {
            $this->emitPatchPoint('before-sanity-check');
            $this->sanityCheck($build_target);
        }
    }

    /**
     * Build cli sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function buildCli(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} /usr/lib/libm.a",
        ]);

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec('sed -ie "s|//lib|/lib|g" Makefile');
        $shell->exec("make -j{$this->concurrency} {$vars} cli");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('strip sapi/cli/php');
        }
        $this->deployBinary(BUILD_TARGET_CLI);
    }

    /**
     * Build phpmicro sapi
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    protected function buildMicro(): void
    {
        if ($this->getPHPVersionID() < 80000) {
            throw new WrongUsageException('phpmicro only support PHP >= 8.0!');
        }
        if ($this->getExt('phar')) {
            $this->phar_patched = true;
            SourcePatcher::patchMicroPhar($this->getPHPVersionID());
        }

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = [
            // with debug information, optimize for size, remove identifiers, patch fake cli for micro
            'EXTRA_CFLAGS' => '-g -Os' . $enable_fake_cli,
            // link resolv library (macOS needs it)
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} /usr/lib/libm.a",
        ];
        $vars = SystemUtil::makeEnvVarString($vars);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} {$vars} micro");

        if (!$this->getOption('no-strip', false)) {
            shell()->cd(SOURCE_PATH . '/php-src/sapi/micro')->exec('strip --strip-all micro.sfx');
        }
        $this->deployBinary(BUILD_TARGET_MICRO);

        if ($this->phar_patched) {
            SourcePatcher::unpatchMicroPhar();
        }
    }

    /**
     * Build fpm sapi
     *
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function buildFpm(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} /usr/lib/libm.a", // link resolv library (macOS needs it)
        ]);

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} fpm");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('strip sapi/fpm/php-fpm');
        }
        $this->deployBinary(BUILD_TARGET_FPM);
    }

    /**
     * Build embed sapi
     *
     * @throws RuntimeException
     */
    protected function buildEmbed(): void
    {
        $vars = SystemUtil::makeEnvVarString([
            'EXTRA_CFLAGS' => '-g -Os', // with debug information, but optimize for size
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} /usr/lib/libm.a", // link resolv library (macOS needs it)
        ]);

        shell()
            ->cd(SOURCE_PATH . '/php-src')
            ->exec('make INSTALL_ROOT=' . BUILD_ROOT_PATH . " -j{$this->concurrency} {$vars} install")
            // Workaround for https://github.com/php/php-src/issues/12082
            ->exec('rm -Rf ' . BUILD_ROOT_PATH . '/lib/php-o')
            ->exec('mkdir ' . BUILD_ROOT_PATH . '/lib/php-o')
            ->cd(BUILD_ROOT_PATH . '/lib/php-o')
            ->exec('ar x ' . BUILD_ROOT_PATH . '/lib/libphp.a')
            ->exec('rm ' . BUILD_ROOT_PATH . '/lib/libphp.a')
            ->exec('ar rcs ' . BUILD_ROOT_PATH . '/lib/libphp.a *.o')
            ->exec('rm -Rf ' . BUILD_ROOT_PATH . '/lib/php-o');
    }
}
