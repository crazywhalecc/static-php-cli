<?php

declare(strict_types=1);

namespace Package\Target\php;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Stage;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\PatchException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\WindowsUtil;
use StaticPHP\Util\V2CompatLayer;
use ZM\Logger\ConsoleColor;

trait windows
{
    #[BeforeStage('php', [self::class, 'buildconfForWindows'])]
    #[PatchDescription('Patch for fixing win32 xml related extensions builds')]
    public function beforeBuildconfWin(TargetPackage $package): void
    {
        FileSystem::replaceFileStr("{$package->getSourceDir()}/win32/build/config.w32", 'dllmain.c ', '');
    }

    #[Stage]
    public function buildconfForWindows(TargetPackage $package, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('./buildconf.bat'));
        V2CompatLayer::emitPatchPoint('before-php-buildconf');
        cmd()->cd($package->getSourceDir())->exec('.\buildconf.bat');

        if ($package->getBuildOption('enable-micro-win32') && $installer->isPackageResolved('php-micro')) {
            SourcePatcher::patchMicroWin32();
        } else {
            SourcePatcher::unpatchMicroWin32();
        }
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
        $embed = $installer->isPackageResolved('php-embed');
        $args[] = $cli ? '--enable-cli=yes' : '--enable-cli=no';
        $args[] = $cgi ? '--enable-cgi=yes' : '--enable-cgi=no';
        $args[] = $micro ? '--enable-micro=yes' : '--enable-micro=no';
        $args[] = $embed ? '--enable-embed=yes' : '--enable-embed=no';

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
    public function makeCliForWindows(TargetPackage $package, PackageBuilder $builder, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('php.exe'));

        // Collect static-libs@windows from all resolved library packages.
        // PHP's configure.bat only adds libs declared by enabled extensions via config.w32;
        // transitive library-only deps (e.g. zlibstatic.lib needed by libcrypto.lib) are
        // not covered. Inject them here so the final link step has all required symbols.
        $resolved_libs = [];
        foreach ($installer->getResolvedPackages(LibraryPackage::class) as $lib) {
            foreach (PackageConfig::get($lib->getName(), 'static-libs', []) as $lib_file) {
                if (file_exists("{$package->getLibDir()}\\{$lib_file}")) {
                    $resolved_libs[] = $lib_file;
                }
            }
        }
        $resolved_libs = array_unique($resolved_libs);

        // extra lib
        $extra_libs = trim((getenv('SPC_EXTRA_LIBS') ?: '') . ' ' . implode(' ', $resolved_libs));
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

    #[BeforeStage('php', [self::class, 'makeCgiForWindows'])]
    #[PatchDescription('Patch Windows Makefile for CGI target')]
    public function patchCGITarget(TargetPackage $package): void
    {
        // search Makefile code line contains "$(BUILD_DIR)\php-cgi.exe:"
        $content = FileSystem::readFile("{$package->getSourceDir()}\\Makefile");
        $lines = explode("\r\n", $content);
        $line_num = 0;
        $found = false;
        foreach ($lines as $v) {
            if (str_contains($v, '$(BUILD_DIR)\php-cgi.exe:')) {
                $found = $line_num;
                break;
            }
            ++$line_num;
        }
        if ($found === false) {
            throw new PatchException('Windows Makefile patching for php-cgi.exe target', 'Cannot patch windows CGI Makefile, Makefile does not contain "$(BUILD_DIR)\php-cgi.exe:" line');
        }
        $lines[$line_num] = '$(BUILD_DIR)\php-cgi.exe: $(DEPS_CGI) $(CGI_GLOBAL_OBJS) $(PHP_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php-cgi.exe.res $(BUILD_DIR)\php-cgi.exe.manifest';
        $lines[$line_num + 1] = "\t" . '@"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CGI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(ASM_OBJS) $(LIBS) $(LIBS_CGI) $(BUILD_DIR)\php-cgi.exe.res /out:$(BUILD_DIR)\php-cgi.exe $(LDFLAGS) $(LDFLAGS_CGI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286';
        FileSystem::writeFile("{$package->getSourceDir()}\\Makefile", implode("\r\n", $lines));

        // Patch cgi-static, comment ZEND_TSRMLS_CACHE_DEFINE()
        FileSystem::replaceFileRegex("{$package->getSourceDir()}\\sapi\\cgi\\cgi_main.c", '/^ZEND_TSRMLS_CACHE_DEFINE\(\)/m', '// ZEND_TSRMLS_CACHE_DEFINE()');
    }

    #[Stage]
    public function makeCgiForWindows(TargetPackage $package, PackageBuilder $builder, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('php-cgi.exe'));

        // Collect static-libs@windows from all resolved library packages.
        $resolved_libs = [];
        foreach ($installer->getResolvedPackages(LibraryPackage::class) as $lib) {
            foreach (PackageConfig::get($lib->getName(), 'static-libs', []) as $lib_file) {
                if (file_exists("{$package->getLibDir()}\\{$lib_file}")) {
                    $resolved_libs[] = $lib_file;
                }
            }
        }
        $resolved_libs = array_unique($resolved_libs);

        // extra lib
        $extra_libs = trim((getenv('SPC_EXTRA_LIBS') ?: '') . ' ' . implode(' ', $resolved_libs));
        // Add debug symbols for release build if --no-strip is specified
        $debug_overrides = '';
        if ($package->getBuildOption('no-strip', false)) {
            $makefile_content = file_get_contents("{$package->getSourceDir()}\\Makefile");
            if (preg_match('/^CFLAGS=(.+?)$/m', $makefile_content, $matches)) {
                $cflags = $matches[1];
                $cflags = str_replace('/Ox ', '/O2 /Zi ', $cflags);
                $debug_overrides = '"CFLAGS=' . $cflags . '" "LDFLAGS=/DEBUG /LTCG /INCREMENTAL:NO" "LDFLAGS_CGI=/DEBUG" ';
            }
        }

        cmd()->cd($package->getSourceDir())
            ->exec("nmake /nologo {$debug_overrides}LIBS_CGI=\"ws2_32.lib kernel32.lib advapi32.lib {$extra_libs}\" EXTRA_LD_FLAGS_PROGRAM= php-cgi.exe");

        $this->deployWindowsBinary($builder, $package, 'php-cgi');
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
        if ($installer->isPackageResolved('php-embed')) {
            $package->runStage([$this, 'makeEmbedForWindows']);
        }
    }

    #[Stage]
    public function makeMicroForWindows(TargetPackage $package, PackageBuilder $builder, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow('micro.sfx'));

        // workaround for fiber (originally from https://github.com/dixyes/lwmbs/blob/master/windows/MicroBuild.php)
        $makefile = FileSystem::readFile("{$package->getSourceDir()}\\Makefile");
        if ($this->getPHPVersionID() >= 80200 && str_contains($makefile, 'FIBER_ASM_ARCH')) {
            $makefile .= "\r\n" . '$(MICRO_SFX): $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ARCH)_ms_pe_masm.obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ARCH)_ms_pe_masm.obj' . "\r\n\r\n";
        } elseif ($this->getPHPVersionID() >= 80400 && str_contains($makefile, 'FIBER_ASM_ABI')) {
            $makefile .= "\r\n" . '$(MICRO_SFX): $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ABI).obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ABI).obj' . "\r\n\r\n";
        }
        FileSystem::writeFile("{$package->getSourceDir()}\\Makefile", $makefile);

        // Collect static-libs@windows from all resolved library packages.
        $resolved_libs = [];
        foreach ($installer->getResolvedPackages(LibraryPackage::class) as $lib) {
            foreach (PackageConfig::get($lib->getName(), 'static-libs', []) as $lib_file) {
                if (file_exists("{$package->getLibDir()}\\{$lib_file}")) {
                    $resolved_libs[] = $lib_file;
                }
            }
        }
        $resolved_libs = array_unique($resolved_libs);

        // extra lib
        $extra_libs = trim((getenv('SPC_EXTRA_LIBS') ?: '') . ' ' . implode(' ', $resolved_libs));
        // Add debug symbols for release build if --no-strip is specified
        $debug_overrides = '';
        if ($package->getBuildOption('no-strip', false)) {
            $makefile_content = file_get_contents("{$package->getSourceDir()}\\Makefile");
            if (preg_match('/^CFLAGS=(.+?)$/m', $makefile_content, $matches)) {
                $cflags = $matches[1];
                $cflags = str_replace('/Ox ', '/O2 /Zi ', $cflags);
                $debug_overrides = '"CFLAGS=' . $cflags . '" "LDFLAGS=/DEBUG /LTCG /INCREMENTAL:NO" "LDFLAGS_MICRO=/DEBUG" ';
            }
        }

        $fake_cli = $package->getBuildOption('with-micro-fake-cli', false) ? ' /DPHP_MICRO_FAKE_CLI' : '';

        // phar patch for micro
        $phar_patched = false;
        if ($installer->isPackageResolved('ext-phar')) {
            $phar_patched = true;
            SourcePatcher::patchMicroPhar(self::getPHPVersionID());
        }

        try {
            cmd()->cd($package->getSourceDir())
                ->exec("nmake /nologo {$debug_overrides}LIBS_MICRO=\"ws2_32.lib shell32.lib {$extra_libs}\" CFLAGS_MICRO=\"/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1{$fake_cli}\" EXTRA_LD_FLAGS_PROGRAM= micro");
        } finally {
            if ($phar_patched) {
                SourcePatcher::unpatchMicroPhar();
            }
        }

        $this->deployWindowsBinary($builder, $package, 'php-micro');
    }

    #[BeforeStage('php', [self::class, 'makeEmbedForWindows'])]
    #[PatchDescription('Patch Windows Makefile for embed static library target')]
    public function patchEmbedTarget(TargetPackage $package): void
    {
        $makefile_path = "{$package->getSourceDir()}\\Makefile";
        $content = FileSystem::readFile($makefile_path);

        // PHP's configure.bat generates PHP_LDFLAGS with /nodefaultlib:libcmt to avoid CRT
        // duplication in a normal /MD build. But our static build compiles everything with /MT,
        // so every .obj file has DEFAULTLIB:LIBCMT embedded. Removing /nodefaultlib:libcmt lets
        // the linker pick up libcmt.lib. We also exclude the dynamic CRT (/nodefaultlib:msvcrt
        // /nodefaultlib:msvcrtd) to keep the DLL dependency-free, consistent with CLI/CGI/micro.
        $content = str_replace(
            'PHP_LDFLAGS=$(DLL_LDFLAGS) /nodefaultlib:libcmt /def:$(PHPDEF)',
            'PHP_LDFLAGS=$(DLL_LDFLAGS) /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /def:$(PHPDEF) /ltcg /ignore:4286',
            $content
        );

        // Patch embed lib target to build a REAL static library instead of just an import lib.
        // The default embed target only includes embed SAPI objects and links against php8.lib (import lib).
        // We need to include PHP core objects (PHP_GLOBAL_OBJS) and static extension objects (STATIC_EXT_OBJS)
        // to create a self-contained static library that doesn't require php8.dll at runtime.
        $major = intdiv($this->getPHPVersionID(), 10000);
        $embed_lib = "php{$major}embed.lib";

        // Find and replace the embed lib build rule
        // Actual Makefile format (note the backslash before $(PHPLIB)):
        // $(BUILD_DIR)\php8embed.lib: $(DEPS_EMBED) $(EMBED_GLOBAL_OBJS) $(BUILD_DIR)\$(PHPLIB) $(BUILD_DIR)\php8embed.lib.res $(BUILD_DIR)\php8embed.lib.manifest
        // 	@$(MAKE_LIB) /nologo /out:$(BUILD_DIR)\php8embed.lib $(ARFLAGS) $(EMBED_GLOBAL_OBJS_RESP) $(BUILD_DIR)\$(PHPLIB) $(ARFLAGS_EMBED) $(LIBS_EMBED) $(BUILD_DIR)\php8embed.lib.res
        $lines = explode("\r\n", $content);
        $new_lines = [];
        $i = 0;
        while ($i < count($lines)) {
            $line = $lines[$i];
            // Check if this is the embed lib target dependency line (contains the lib name and $(BUILD_DIR)\$(PHPLIB))
            if (str_contains($line, "\$(BUILD_DIR)\\{$embed_lib}:") && str_contains($line, '$(BUILD_DIR)\$(PHPLIB)')) {
                // Replace the dependency line
                // Original: $(BUILD_DIR)\php8embed.lib: $(DEPS_EMBED) $(EMBED_GLOBAL_OBJS) $(BUILD_DIR)\$(PHPLIB) $(BUILD_DIR)\php8embed.lib.res $(BUILD_DIR)\php8embed.lib.manifest
                // New: $(BUILD_DIR)\php8embed.lib: $(DEPS_EMBED) $(EMBED_GLOBAL_OBJS) $(PHP_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php8embed.lib.res $(BUILD_DIR)\php8embed.lib.manifest
                $new_deps = "\$(BUILD_DIR)\\{$embed_lib}: \$(DEPS_EMBED) \$(EMBED_GLOBAL_OBJS) \$(PHP_GLOBAL_OBJS) \$(STATIC_EXT_OBJS) \$(ASM_OBJS) \$(BUILD_DIR)\\{$embed_lib}.res \$(BUILD_DIR)\\{$embed_lib}.manifest";
                $new_lines[] = $new_deps;
                // Skip the original line (we replaced it)
                ++$i;
                // Now look for the lib.exe command line (should be the next non-empty line starting with tab)
                while ($i < count($lines) && trim($lines[$i]) === '') {
                    $new_lines[] = $lines[$i];
                    ++$i;
                }
                // Replace the lib.exe command to include PHP_GLOBAL_OBJS_RESP and STATIC_EXT_OBJS_RESP
                // Original: @$(MAKE_LIB) /nologo /out:$(BUILD_DIR)\php8embed.lib $(ARFLAGS) $(EMBED_GLOBAL_OBJS_RESP) $(BUILD_DIR)\$(PHPLIB) $(ARFLAGS_EMBED) $(LIBS_EMBED) $(BUILD_DIR)\php8embed.lib.res
                // New: @$(MAKE_LIB) /nologo /out:$(BUILD_DIR)\php8embed.lib $(ARFLAGS) $(EMBED_GLOBAL_OBJS_RESP) $(PHP_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(ASM_OBJS) $(STATIC_EXT_LIBS) $(ARFLAGS_EMBED) $(LIBS_EMBED) $(BUILD_DIR)\php8embed.lib.res
                if ($i < count($lines) && str_contains($lines[$i], '$(MAKE_LIB)')) {
                    $cmd_line = $lines[$i];
                    // Remove $(BUILD_DIR)\$(PHPLIB) from the command (note the backslash)
                    $cmd_line = str_replace(' $(BUILD_DIR)\$(PHPLIB)', '', $cmd_line);
                    // Add PHP_GLOBAL_OBJS_RESP and STATIC_EXT_OBJS_RESP after EMBED_GLOBAL_OBJS_RESP
                    $cmd_line = str_replace(
                        '$(EMBED_GLOBAL_OBJS_RESP)',
                        '$(EMBED_GLOBAL_OBJS_RESP) $(PHP_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(ASM_OBJS) $(STATIC_EXT_LIBS)',
                        $cmd_line
                    );
                    $new_lines[] = $cmd_line;
                    ++$i;
                }
            } else {
                $new_lines[] = $line;
                ++$i;
            }
        }
        $content = implode("\r\n", $new_lines);

        FileSystem::writeFile($makefile_path, $content);
    }

    #[Stage]
    public function makeEmbedForWindows(TargetPackage $package, PackageBuilder $builder, PackageInstaller $installer): void
    {
        $major = intdiv($this->getPHPVersionID(), 10000);
        $embed_lib = "php{$major}embed.lib";
        InteractiveTerm::setMessage('Building php: ' . ConsoleColor::yellow($embed_lib));

        // Add debug symbols for release build if --no-strip is specified
        $debug_overrides = '';
        if ($package->getBuildOption('no-strip', false)) {
            $makefile_content = file_get_contents("{$package->getSourceDir()}\\Makefile");
            if (preg_match('/^CFLAGS=(.+?)$/m', $makefile_content, $matches)) {
                $cflags = $matches[1];
                $cflags = str_replace('/Ox ', '/O2 /Zi ', $cflags);
                $debug_overrides = '"CFLAGS=' . $cflags . '" "LDFLAGS=/DEBUG /LTCG /INCREMENTAL:NO" ';
            }
        }

        // Build the embed static library (patched to include PHP core and extension objects)
        cmd()->cd($package->getSourceDir())
            ->exec("nmake /nologo {$debug_overrides}{$embed_lib}");

        // Deploy: php8embed.lib is now a REAL static library containing all PHP code
        $rel_type = 'Release'; // TODO: Debug build support
        $ts = $builder->getOption('enable-zts') ? '_TS' : '';
        $build_dir = "{$package->getSourceDir()}\\x64\\{$rel_type}{$ts}";

        // copy static embed lib to buildroot/lib
        $embed_lib_src = "{$build_dir}\\{$embed_lib}";
        if (file_exists($embed_lib_src)) {
            FileSystem::copy($embed_lib_src, "{$package->getLibDir()}\\{$embed_lib}");
            $package->setOutput('Static library path for embed SAPI', "{$package->getLibDir()}\\{$embed_lib}");
        }

        // Note: We no longer deploy php8.dll because the embed static library is self-contained.
        // All PHP core code, extensions, and embed SAPI are statically linked into php8embed.lib.

        // copy .pdb debug info if --no-strip
        $debug_dir = BUILD_ROOT_PATH . '\debug';
        if ($builder->getOption('no-strip', false)) {
            $pdb = "{$build_dir}\\php{$major}embed.pdb";
            if (file_exists($pdb)) {
                FileSystem::createDir($debug_dir);
                FileSystem::copy($pdb, "{$debug_dir}\\php{$major}embed.pdb");
            }
        }

        // Install PHP headers for embed SAPI development
        $this->installPhpHeadersForWindows($package, $installer);
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
            copy("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c", "{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak");
            FileSystem::replaceFileStr("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c", '#include "php_variables.h"', '#include "php_variables.h"' . "\n#define PHP_MICRO_WIN32_NO_CONSOLE 1");
        } else {
            if (file_exists("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak")) {
                rename("{$package->getSourceDir()}\\sapi\\micro\\php_micro.c.win32bak", "{$package->getSourceDir()}\\sapi\\micro\\php_micro.c");
            }
        }
    }

    #[Stage]
    public function smokeTestForWindows(PackageBuilder $builder, TargetPackage $package, PackageInstaller $installer): void
    {
        // analyse --no-smoke-test option
        $no_smoke_test = $builder->getOption('no-smoke-test');
        $option = match ($no_smoke_test) {
            false => false,
            null => 'all',
            default => parse_comma_list($no_smoke_test),
        };
        $valid_tests = ['cli', 'cgi', 'micro', 'micro-exts', 'embed'];
        // compat: --without-micro-ext-test is equivalent to --no-smoke-test=micro-exts
        if ($builder->getOption('without-micro-ext-test', false)) {
            $valid_tests = array_diff($valid_tests, ['micro-exts']);
        }
        if (is_array($option)) {
            foreach ($option as $test) {
                if (!in_array($test, $valid_tests, true)) {
                    throw new WrongUsageException("Invalid value for --no-smoke-test: {$test}. Valid values are: " . implode(', ', $valid_tests));
                }
                $valid_tests = array_diff($valid_tests, [$test]);
            }
        } elseif ($option === 'all') {
            $valid_tests = [];
        }

        // remove all .dll from buildroot/bin/
        $dlls = glob(BUILD_BIN_PATH . '\*.dll') ?: [];
        foreach ($dlls as $dll) {
            @unlink($dll);
        }

        if (in_array('cli', $valid_tests, true) && $installer->isPackageResolved('php-cli')) {
            $package->runStage([$this, 'smokeTestCliForWindows']);
        }
        if (in_array('cgi', $valid_tests, true) && $installer->isPackageResolved('php-cgi')) {
            $package->runStage([$this, 'smokeTestCgiForWindows']);
        }
        if (in_array('micro', $valid_tests, true) && $installer->isPackageResolved('php-micro')) {
            $skipExtTest = !in_array('micro-exts', $valid_tests, true);
            $package->runStage([$this, 'smokeTestMicroForWindows'], ['skipExtTest' => $skipExtTest]);
        }
        if (in_array('embed', $valid_tests, true) && $installer->isPackageResolved('php-embed')) {
            $package->runStage([$this, 'smokeTestEmbedForWindows'], ['installer' => $installer]);
        }
    }

    #[Stage]
    public function smokeTestCliForWindows(PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Running basic php-cli smoke test');
        [$ret, $output] = cmd()->execWithResult(BUILD_BIN_PATH . '\php.exe -n -r "echo \"hello\";"');
        $raw_output = implode('', $output);
        if ($ret !== 0 || trim($raw_output) !== 'hello') {
            throw new ValidationException("cli failed smoke test. code: {$ret}, output: {$raw_output}", validation_module: 'php-cli smoke test');
        }

        $exts = $installer->getResolvedPackages(PhpExtensionPackage::class);
        foreach ($exts as $ext) {
            InteractiveTerm::setMessage('Running php-cli smoke test for ' . ConsoleColor::yellow($ext->getExtensionName()) . ' extension');
            $ext->runSmokeTestCliWindows();
        }
    }

    #[Stage]
    public function smokeTestCgiForWindows(): void
    {
        InteractiveTerm::setMessage('Running basic php-cgi smoke test');
        FileSystem::writeFile(SOURCE_PATH . '\php-cgi-test.php', '<?php echo "<h1>Hello, World!</h1>"; ?>');
        [$ret, $output] = cmd()->execWithResult(BUILD_BIN_PATH . '\php-cgi.exe -n -f ' . SOURCE_PATH . '\php-cgi-test.php');
        $raw_output = implode("\n", $output);
        if ($ret !== 0 || !str_contains($raw_output, 'Hello, World!')) {
            throw new ValidationException("cgi failed smoke test. code: {$ret}, output: {$raw_output}", validation_module: 'php-cgi smoke test');
        }
    }

    #[Stage]
    public function smokeTestMicroForWindows(PackageInstaller $installer, bool $skipExtTest = false): void
    {
        $micro_sfx = BUILD_BIN_PATH . '\micro.sfx';

        InteractiveTerm::setMessage('Running php-micro smoke test');
        $content = $skipExtTest
            ? '<?php echo "[micro-test-start][micro-test-end]";'
            : $this->generateMicroExtTests($installer);
        $test_file = SOURCE_PATH . '\micro_ext_test.exe';
        if (file_exists($test_file)) {
            @unlink($test_file);
        }
        file_put_contents($test_file, file_get_contents($micro_sfx) . $content);
        [$ret, $out] = cmd()->execWithResult($test_file);
        $raw_out = trim(implode('', $out));
        if ($ret !== 0 || !str_starts_with($raw_out, '[micro-test-start]') || !str_ends_with($raw_out, '[micro-test-end]')) {
            throw new ValidationException(
                "micro_ext_test failed. code: {$ret}, output: {$raw_out}",
                validation_module: 'phpmicro sanity check item [micro_ext_test]'
            );
        }
    }

    #[Stage]
    public function smokeTestEmbedForWindows(PackageInstaller $installer, TargetPackage $package): void
    {
        $test_dir = SOURCE_PATH . '\embed-test';
        FileSystem::createDir($test_dir);

        // Create embed.c test file (Windows version)
        $embed_c = <<<'C_CODE'
#include <sapi/embed/php_embed.h>

int main(int argc, char **argv) {
    PHP_EMBED_START_BLOCK(argc, argv)

    zend_file_handle file_handle;
    zend_stream_init_filename(&file_handle, "embed.php");

    if (!php_execute_script(&file_handle)) {
        php_printf("Failed to execute PHP script.\n");
    }

    PHP_EMBED_END_BLOCK()
    return 0;
}
C_CODE;
        FileSystem::writeFile($test_dir . '\embed.c', $embed_c);

        // Create embed.php test file
        FileSystem::writeFile($test_dir . '\embed.php', "<?php\n\ndeclare(strict_types=1);\necho 'hello' . PHP_EOL;\n");

        // Get build configuration using spc-config
        $util = new \StaticPHP\Util\SPCConfigUtil();
        $config = $util->config(array_map(fn ($x) => $x->getName(), $installer->getResolvedPackages()));

        // Build the embed test executable using cl.exe
        // Note: MSVCToolchain already initialized the VC environment, no need for vcvarsall
        InteractiveTerm::setMessage('Running php-embed build smoke test');

        // For Windows, we need to use PHP source directory headers directly
        // because Windows PHP doesn't use php_config.h like Unix
        $source_dir = $package->getSourceDir();
        $rel_type = 'Release';
        $ts = $package->getBuildOption('enable-zts', false) ? '_TS' : '';
        $build_dir = "{$source_dir}\\x64\\{$rel_type}{$ts}";

        // Build include flags pointing to source dirs (like PHP Windows build does)
        // Note: embed.c uses #include <sapi/embed/php_embed.h>, so we need $source_dir itself
        $include_flags = sprintf(
            '/I"%s" /I"%s\main" /I"%s\Zend" /I"%s\TSRM" /I"%s" ' .
            '/D ZEND_WIN32=1 /D PHP_WIN32=1 /D WIN32 /D _WINDOWS /D WINDOWS=1 /D _MBCS /D _USE_MATH_DEFINES',
            $build_dir,
            $source_dir,
            $source_dir,
            $source_dir,
            $source_dir
        );

        // MSVC cl.exe format: compiler flags must come before /link, linker flags after
        // ldflags contains /LIBPATH which must be after /link
        $compile_cmd = sprintf(
            'cl.exe /nologo /O2 /MT /Z7 %s embed.c /Fe:embed.exe /link /LIBPATH:"%s\lib" %s %s',
            $include_flags,
            BUILD_ROOT_PATH,
            $config['libs'],
            'kernel32.lib ole32.lib user32.lib advapi32.lib shell32.lib ws2_32.lib dnsapi.lib psapi.lib bcrypt.lib'  // Windows system libs (match Makefile LIBS)
        );

        // Log command explicitly (workaround for cmd() not logging complex commands properly)
        logger()->debug('Embed smoke test compile command: ' . $compile_cmd);

        [$ret, $out] = cmd()->cd($test_dir)->execWithResult($compile_cmd);
        if ($ret !== 0) {
            throw new ValidationException(
                'embed failed to build. Error message: ' . implode("\n", $out),
                validation_module: 'php-embed build smoke test'
            );
        }

        // Run the embed test
        InteractiveTerm::setMessage('Running php-embed run smoke test');
        [$ret, $output] = cmd()->cd($test_dir)->execWithResult('embed.exe');
        $raw_output = implode('', $output);
        if ($ret !== 0 || trim($raw_output) !== 'hello') {
            throw new ValidationException(
                'embed failed to run. Error message: ' . $raw_output,
                validation_module: 'php-embed run smoke test'
            );
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

        $output_label = match ($sapi) {
            'php-cli' => 'Binary path for cli SAPI',
            'php-cgi' => 'Binary path for cgi SAPI',
            'php-micro' => 'Binary path for micro SAPI',
            default => null,
        };
        if ($output_label) {
            $package->setOutput($output_label, $dst_file);
        }

        // copy .pdb debug info file
        if ($builder->getOption('no-strip', false) && file_exists("{$src[0]}\\{$src[2]}")) {
            FileSystem::createDir($debug_dir);
            FileSystem::copy("{$src[0]}\\{$src[2]}", "{$debug_dir}\\{$src[2]}");
        }
    }

    /**
     * Install PHP headers to buildroot/include for embed SAPI development.
     * This mirrors the 'make install-headers' behavior on Unix.
     */
    private function installPhpHeadersForWindows(TargetPackage $package, PackageInstaller $installer): void
    {
        InteractiveTerm::setMessage('Installing PHP headers for embed SAPI');

        $source_dir = $package->getSourceDir();
        $include_dir = $package->getIncludeDir();
        $php_include_dir = "{$include_dir}\\php";

        // Create directory structure
        FileSystem::createDir("{$php_include_dir}\\main");
        FileSystem::createDir("{$php_include_dir}\\Zend");
        FileSystem::createDir("{$php_include_dir}\\TSRM");
        FileSystem::createDir("{$php_include_dir}\\sapi\\embed");

        // Copy main/*.h
        foreach (glob("{$source_dir}\\main\\*.h") as $h) {
            FileSystem::copy($h, "{$php_include_dir}\\main\\" . basename($h));
        }

        // Copy Zend/*.h
        foreach (glob("{$source_dir}\\Zend\\*.h") as $h) {
            $target = "{$php_include_dir}\\Zend\\" . basename($h);
            FileSystem::copy($h, $target);
            // Fix GCC-specific #warning directive not supported by MSVC
            if (basename($h) === 'zend_atomic.h') {
                FileSystem::replaceFileStr($target, '#warning No atomics support detected. Please open an issue with platform details.', '#pragma message("No atomics support detected. Please open an issue with platform details.")');
            }
        }

        // Copy TSRM/*.h
        foreach (glob("{$source_dir}\\TSRM\\*.h") as $h) {
            FileSystem::copy($h, "{$php_include_dir}\\TSRM\\" . basename($h));
        }

        // Copy embed SAPI header
        FileSystem::copy("{$source_dir}\\sapi\\embed\\php_embed.h", "{$php_include_dir}\\sapi\\embed\\php_embed.h");

        // Copy generated config.h (config.w32.h on Windows) to php_config.h
        $rel_type = 'Release';
        $ts = $package->getBuildOption('enable-zts', false) ? '_TS' : '';
        $build_dir = "{$source_dir}\\x64\\{$rel_type}{$ts}";

        // Always copy config.w32.h from source (it's used for both build and headers)
        if (file_exists("{$source_dir}\\main\\config.w32.h")) {
            FileSystem::copy("{$source_dir}\\main\\config.w32.h", "{$php_include_dir}\\main\\php_config.h");
        }

        // Windows: zend_config.w32.h must be copied as zend_config.h for Zend headers to work
        if (file_exists("{$source_dir}\\Zend\\zend_config.w32.h")) {
            FileSystem::copy("{$source_dir}\\Zend\\zend_config.w32.h", "{$php_include_dir}\\Zend\\zend_config.h");
        }

        // Copy extension headers for enabled extensions
        foreach ($installer->getResolvedPackages(PhpExtensionPackage::class) as $ext) {
            $ext_name = $ext->getExtensionName();
            $ext_dir = "{$source_dir}\\ext\\{$ext_name}";
            if (is_dir($ext_dir)) {
                $target_ext_dir = "{$php_include_dir}\\ext\\{$ext_name}";
                FileSystem::createDir($target_ext_dir);
                foreach (glob("{$ext_dir}\\*.h") as $h) {
                    FileSystem::copy($h, "{$target_ext_dir}\\" . basename($h));
                }
                // Also copy any arginfo headers
                foreach (glob("{$ext_dir}\\*_arginfo.h") as $h) {
                    if (!file_exists("{$target_ext_dir}\\" . basename($h))) {
                        FileSystem::copy($h, "{$target_ext_dir}\\" . basename($h));
                    }
                }
            }
        }

        $package->setOutput('PHP headers path for embed SAPI', $php_include_dir);
    }
}
