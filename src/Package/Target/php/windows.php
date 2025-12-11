<?php

declare(strict_types=1);

namespace Package\Target\php;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\PatchException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\WindowsUtil;
use StaticPHP\Util\V2CompatLayer;
use ZM\Logger\ConsoleColor;

trait windows
{
    #[Stage]
    public function buildconfForWindows(TargetPackage $package): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./buildconf.bat'));
        V2CompatLayer::emitPatchPoint('before-php-buildconf');
        cmd()->cd($package->getSourceDir())->exec('.\buildconf.bat');
    }

    #[Stage]
    public function configureForWindows(TargetPackage $package, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./configure.bat'));
        V2CompatLayer::emitPatchPoint('before-php-configure');
        $args = [
            '--disable-all',
            "--with-php-build={$package->getBuildRootPath()}",
            "--with-extra-includes={$package->getIncludeDir()}",
            "--with-extra-libs={$package->getLibDir()}",
        ];
        // sapis
        $cli = $installer->isPackageResolved('php-cli');
        $cgi = $installer->isPackageResolved('php-cgi');
        $micro = $installer->isPackageResolved('php-micro');
        $args[] = $cli ? '--enable-cli=yes' : '--enable-cli=no';
        $args[] = $cgi ? '--enable-cgi=yes' : '--enable-cgi=no';
        $args[] = $micro ? '--enable-micro=yes' : '--enable-micro=no';

        // zts
        $args[] = $package->getBuildOption('enable-zts', false) ? '--enable-zts=yes' : '--enable-zts=no';
        // opcache-jit
        $args[] = !$package->getBuildOption('disable-opcache-jit', false) ? '--enable-opcache-jit=yes' : '--enable-opcache-jit=no';
        // micro win32
        if ($micro && $package->getBuildOption('enable-micro-win32', false)) {
            $args[] = '--enable-micro-win32=yes';
        }
        // config-file-scan-dir
        if ($option = $package->getBuildOption('with-config-file-scan-dir', false)) {
            $args[] = "--with-config-file-scan-dir={$option}";
        }
        // micro logo
        if ($micro && ($logo = $this->getBuildOption('with-micro-logo')) !== null) {
            $args[] = "--enable-micro-logo={$logo}";
            copy($logo, SOURCE_PATH . '\php-src\\' . $logo);
        }
        $args = implode(' ', $args);
        $static_extension_str = $this->makeStaticExtensionString($installer);
        cmd()->cd($package->getSourceDir())->exec(".\\configure.bat {$args} {$static_extension_str}");
    }

    #[BeforeStage('php', [self::class, 'makeCliForWindows'])]
    #[PatchDescription('Patch Windows Makefile for CLI target')]
    public function patchCLITarget(TargetPackage $package): void
    {
        // search Makefile code line contains "$(BUILD_DIR)\php.exe:"
        $content = FileSystem::readFile("{$package->getSourceDir()}\\Makefile");
        $lines = explode("\r\n", $content);
        $line_num = 0;
        $found = false;
        foreach ($lines as $v) {
            if (str_contains($v, '$(BUILD_DIR)\php.exe:')) {
                $found = $line_num;
                break;
            }
            ++$line_num;
        }
        if ($found === false) {
            throw new PatchException('Windows Makefile patching for php.exe target', 'Cannot patch windows CLI Makefile, Makefile does not contain "$(BUILD_DIR)\php.exe:" line');
        }
        $lines[$line_num] = '$(BUILD_DIR)\php.exe: generated_files $(DEPS_CLI) $(PHP_GLOBAL_OBJS) $(CLI_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php.exe.res $(BUILD_DIR)\php.exe.manifest';
        $lines[$line_num + 1] = "\t" . '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(ASM_OBJS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286';
        FileSystem::writeFile("{$package->getSourceDir()}\\Makefile", implode("\r\n", $lines));
    }

    #[Stage]
    public function makeCliForWindows(TargetPackage $package, PackageBuilder $builder): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('php.exe'));

        // extra lib
        $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';

        // Add debug symbols for release build if --no-strip is specified
        // We need to modify CFLAGS to replace /Ox with /Zi and add /DEBUG to LDFLAGS
        $debug_overrides = '';
        if ($package->getBuildOption('no-strip', false)) {
            // Read current CFLAGS from Makefile and replace optimization flags
            $makefile_content = file_get_contents("{$package->getSourceDir()}\\Makefile");
            if (preg_match('/^CFLAGS=(.+?)$/m', $makefile_content, $matches)) {
                $cflags = $matches[1];
                // Replace /Ox (full optimization) with /Zi (debug info) and /Od (disable optimization)
                // Keep optimization for speed: /O2 /Zi instead of /Od /Zi
                $cflags = str_replace('/Ox ', '/O2 /Zi ', $cflags);
                $debug_overrides = '"CFLAGS=' . $cflags . '" "LDFLAGS=/DEBUG /LTCG /INCREMENTAL:NO" "LDFLAGS_CLI=/DEBUG" ';
            }
        }

        cmd()->cd($package->getSourceDir())
            ->exec("nmake /nologo {$debug_overrides}LIBS_CLI=\"ws2_32.lib shell32.lib {$extra_libs}\" EXTRA_LD_FLAGS_PROGRAM= php.exe");

        $this->deployWindowsBinary($builder, $package, 'php-cli');
    }

    #[Stage]
    public function makeForWindows(TargetPackage $package, PackageInstaller $installer): void
    {
        V2CompatLayer::emitPatchPoint('before-php-make');
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('nmake clean'));
        cmd()->cd($package->getSourceDir())->exec('nmake clean');

        if ($installer->isPackageResolved('php-cli')) {
            $package->runStage([$this, 'makeCliForWindows']);
        }
        if ($installer->isPackageResolved('php-cgi')) {
            $package->runStage([$this, 'makeCgiForWindows']);
        }
        if ($installer->isPackageResolved('php-micro')) {
            $package->runStage([$this, 'makeMicroForWindows']);
        }
    }

    #[BuildFor('Windows')]
    public function buildWin(TargetPackage $package): void
    {
        if ($package->getName() !== 'php') {
            return;
        }

        $package->runStage([$this, 'buildconfForWindows']);
        $package->runStage([$this, 'configureForWindows']);
        $package->runStage([$this, 'makeForWindows']);
    }

    #[BeforeStage('php', [self::class, 'buildconfForWindows'])]
    #[PatchDescription('Patch SPC_MICRO_PATCHES defined patches')]
    #[PatchDescription('Fix PHP 8.1 static build bug on Windows')]
    #[PatchDescription('Fix PHP Visual Studio version detection')]
    public function patchBeforeBuildconfForWindows(TargetPackage $package): void
    {
        // php-src patches from micro
        SourcePatcher::patchPhpSrc();

        // php 8.1 bug
        if ($this->getPHPVersionID() >= 80100 && $this->getPHPVersionID() < 80200) {
            logger()->info('Patching PHP 8.1 windows Fiber bug');
            FileSystem::replaceFileStr(
                "{$package->getSourceDir()}\\win32\\build\\config.w32",
                "ADD_FLAG('LDFLAGS', '$(BUILD_DIR)\\\\Zend\\\\jump_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');",
                "ADD_FLAG('ASM_OBJS', '$(BUILD_DIR)\\\\Zend\\\\jump_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj $(BUILD_DIR)\\\\Zend\\\\make_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');"
            );
            FileSystem::replaceFileStr(
                "{$package->getSourceDir()}\\win32\\build\\config.w32",
                "ADD_FLAG('LDFLAGS', '$(BUILD_DIR)\\\\Zend\\\\make_' + FIBER_ASM_ARCH + '_ms_pe_masm.obj');",
                ''
            );
        }

        // Fix PHP VS version
        // get vs version
        $vc = WindowsUtil::findVisualStudio();
        if ($vc === false) {
            $vc_matches = ['unknown', 'unknown'];
        } else {
            $vc_matches = match ($vc['major_version']) {
                '17' => ['VS17', 'Visual C++ 2022'],
                '16' => ['VS16', 'Visual C++ 2019'],
                default => ['unknown', 'unknown'],
            };
        }
        // patch php-src/win32/build/confutils.js
        FileSystem::replaceFileStr(
            "{$package->getSourceDir()}\\win32\\build\\confutils.js",
            'var name = "unknown";',
            "var name = short ? \"{$vc_matches[0]}\" : \"{$vc_matches[1]}\";return name;"
        );

        // patch micro win32
        if ($package->getBuildOption('enable-micro-win32') && !file_exists("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak")) {
            copy("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c", "{$package->getSourceDir()}\\php-src\\sapi\\micro\\php_micro.c.win32bak");
            FileSystem::replaceFileStr("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c", '#include "php_variables.h"', '#include "php_variables.h"' . "\n#define PHP_MICRO_WIN32_NO_CONSOLE 1");
        } else {
            if (file_exists("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak")) {
                rename("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak", "{$package->getSourceDir()}\\sapi\\micro\\php_micro.c");
            }
        }
    }

    protected function deployWindowsBinary(PackageBuilder $builder, TargetPackage $package, string $sapi): void
    {
        $rel_type = 'Release'; // TODO: Debug build support
        $ts = $builder->getOption('enable-zts') ? '_TS' : '';
        $debug_dir = BUILD_ROOT_PATH . '\debug';
        $src = match ($sapi) {
            'php-cli' => ["{$package->getSourceDir()}\\x64\\{$rel_type}{$ts}", 'php.exe', 'php.pdb'],
            'php-micro' => ["{$package->getSourceDir()}\\x64\\{$rel_type}{$ts}", 'micro.sfx', 'micro.pdb'],
            'php-cgi' => ["{$package->getSourceDir()}\\x64\\{$rel_type}{$ts}", 'php-cgi.exe', 'php-cgi.pdb'],
            default => throw new SPCInternalException("Deployment does not accept type {$sapi}"),
        };
        $src_file = "{$src[0]}\\{$src[1]}";
        $dst_file = BUILD_BIN_PATH . '\\' . basename($src_file);

        $builder->deployBinary($src_file, $dst_file);

        // make debug info file path
        if ($builder->getOption('no-strip', false) && file_exists("{$src[0]}\\{$src[2]}")) {
            FileSystem::copy("{$src[0]}\\{$src[2]}", "{$debug_dir}\\{$src[2]}");
        }
    }
}
