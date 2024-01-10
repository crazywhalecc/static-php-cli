<?php

declare(strict_types=1);

namespace SPC\builder\macos;

use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\builder\unix\UnixBuilderBase;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

class MacOSBuilder extends UnixBuilderBase
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
        // set C++ Composer (default: clang++)
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
        $this->cmake_toolchain_file = SystemUtil::makeCmakeToolchainFile('Darwin', $this->getOption('arch'), $this->arch_c_flags);

        // create pkgconfig and include dir (some libs cannot create them automatically)
        f_mkdir(BUILD_LIB_PATH . '/pkgconfig', recursive: true);
        f_mkdir(BUILD_INCLUDE_PATH, recursive: true);
    }

    /**
     * [deprecated] 生成库构建采用的 autoconf 参数列表
     *
     * @param string $name      要构建的 lib 库名，传入仅供输出日志
     * @param array  $lib_specs 依赖的 lib 库的 autoconf 文件
     */
    public function makeAutoconfArgs(string $name, array $lib_specs): string
    {
        $ret = '';
        foreach ($lib_specs as $libName => $arr) {
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0] ?? null;
            if ($lib instanceof MacOSLibraryBase) {
                logger()->info("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= '--with-' . $libName . '=yes ';
            } else {
                logger()->info("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-{$libName}=no") . ' ';
            }
        }
        return rtrim($ret);
    }

    /**
     * Get dynamically linked macOS frameworks
     *
     * @param  bool                $asString If true, return as string
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    public function getFrameworks(bool $asString = false): array|string
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                $libs[] = $dep;
            }
            $libs[] = $lib;
        }

        $frameworks = [];
        /** @var MacOSLibraryBase $lib */
        foreach ($libs as $lib) {
            array_push($frameworks, ...$lib->getFrameworks());
        }

        if ($asString) {
            return implode(' ', array_map(fn ($x) => "-framework {$x}", $frameworks));
        }
        return $frameworks;
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
        // add macOS frameworks
        $extra_libs .= (empty($extra_libs) ? '' : ' ') . $this->getFrameworks(true);
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
        $zts = $this->getOption('enable-zts', false) ? '--enable-zts --disable-zend-signals ' : '';

        $enableCli = ($build_target & BUILD_TARGET_CLI) === BUILD_TARGET_CLI;
        $enableFpm = ($build_target & BUILD_TARGET_FPM) === BUILD_TARGET_FPM;
        $enableMicro = ($build_target & BUILD_TARGET_MICRO) === BUILD_TARGET_MICRO;
        $enableEmbed = ($build_target & BUILD_TARGET_EMBED) === BUILD_TARGET_EMBED;

        // prepare build php envs
        $envs_build_php = SystemUtil::makeEnvVarString([
            'CFLAGS' => " {$this->arch_c_flags} -Werror=unknown-warning-option ",
            'CPPFLAGS' => '-I' . BUILD_INCLUDE_PATH,
            'LDFLAGS' => '-L' . BUILD_LIB_PATH,
        ]);

        if ($this->getLib('postgresql')) {
            shell()
                ->cd(SOURCE_PATH . '/php-src')
                ->exec(
                    'sed -i.backup "s/ac_cv_func_explicit_bzero\" = xyes/ac_cv_func_explicit_bzero\" = x_fake_yes/" ./configure'
                );
        }

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec(
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .     // Not detect memory leak
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                ($enableCli ? '--enable-cli ' : '--disable-cli ') .
                ($enableFpm ? '--enable-fpm ' : '--disable-fpm ') .
                ($enableEmbed ? '--enable-embed=static ' : '--disable-embed ') .
                ($enableMicro ? '--enable-micro ' : '--disable-micro ') .
                $json_74 .
                $zts .
                $this->makeExtensionArgs() . ' ' .
                $envs_build_php
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
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} cli");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/cli/php')->exec('strip sapi/cli/php');
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
            SourcePatcher::patchMicro(['phar']);
        }

        $enable_fake_cli = $this->getOption('with-micro-fake-cli', false) ? ' -DPHP_MICRO_FAKE_CLI' : '';
        $vars = [
            // with debug information, optimize for size, remove identifiers, patch fake cli for micro
            'EXTRA_CFLAGS' => '-g -Os -fno-ident' . $enable_fake_cli,
        ];
        $vars = $this->getBuildVars($vars);
        if (!$this->getOption('no-strip', false)) {
            $vars['STRIP'] = 'dsymutil -f ';
        }
        $vars = SystemUtil::makeEnvVarString($vars);

        shell()->cd(SOURCE_PATH . '/php-src')
            ->exec("make -j{$this->concurrency} {$vars} micro");
        $this->deployBinary(BUILD_TARGET_MICRO);

        if ($this->phar_patched) {
            SourcePatcher::patchMicro(['phar'], true);
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
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());

        $shell = shell()->cd(SOURCE_PATH . '/php-src');
        $shell->exec("make -j{$this->concurrency} {$vars} fpm");
        if (!$this->getOption('no-strip', false)) {
            $shell->exec('dsymutil -f sapi/fpm/php-fpm')->exec('strip sapi/fpm/php-fpm');
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
        $vars = SystemUtil::makeEnvVarString($this->getBuildVars());

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

    private function getBuildVars($input = []): array
    {
        $optimization = $this->getOption('no-strip', false) ? '-g -O0' : '-g0 -Os';
        $cflags = isset($input['EXTRA_CFLAGS']) && $input['EXTRA_CFLAGS'] ? " {$input['EXTRA_CFLAGS']}" : '';
        $libs = isset($input['EXTRA_LIBS']) && $input['EXTRA_LIBS'] ? " {$input['EXTRA_LIBS']}" : '';
        return [
            'EXTRA_CFLAGS' => "{$optimization} {$cflags} " . $this->getOption('x-extra-cflags'),
            'EXTRA_LIBS' => "{$this->getOption('extra-libs')} -lresolv {$libs} " . $this->getOption('x-extra-libs'),
        ];
    }
}
