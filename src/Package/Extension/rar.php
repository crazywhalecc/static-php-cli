<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\SourcePatcher;

#[Extension('rar')]
class rar extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('rar extension workaround for newer Xcode clang (>= 15.0)')]
    public function patchBeforeBuildconf(): void
    {
        // workaround for newer Xcode clang (>= 15.0)
        if (SystemTarget::getTargetOS() === 'Darwin') {
            FileSystem::replaceFileStr("{$this->getBuildDir()}/config.m4", '-Wall -fvisibility=hidden', '-Wall -Wno-incompatible-function-pointer-types -fvisibility=hidden');
        }
    }

    /**
     * php-rar 4.3.1+ GitHub release tarballs omit rar.map, but config.m4 probes
     * and links every build with -Wl,--version-script=$ext_srcdir/rar.map. The
     * configure probe links an executable, where ld.lld tolerates the missing
     * file, so configure reports "yes" and the real shared-library link fails
     * with "cannot find version script". Recreate the file (content matches
     * upstream master) when missing, before configure on both the shared
     * phpize path and the static in-tree path.
     */
    #[BeforeStage('ext-rar', [self::class, 'configureForUnix'])]
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('Recreate rar.map omitted from php-rar release tarballs but required by config.m4')]
    public function patchMissingRarMap(): void
    {
        $mapFile = "{$this->getBuildDir()}/rar.map";
        if (!file_exists($mapFile)) {
            FileSystem::writeFile($mapFile, "{ global: get_module; local: *; };\n");
        }
    }

    /**
     * unrar's __builtin_cpu_supports() (GCC extension) resolves to __cpu_model at
     * link time, which zig's compiler-rt does not provide, so static builds (and,
     * with -z defs, shared ones) fail with "undefined symbol: __cpu_model".
     * Patch both __GNUC__ fallbacks (unrar/system.cpp, unrar/rijndael.cpp) to
     * __get_cpuid(), the same logic unrar already uses for _MSC_VER. Patch before
     * configure on both the shared phpize path and the static in-tree path.
     */
    #[BeforeStage('ext-rar', [self::class, 'configureForUnix'])]
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rar')]
    #[PatchDescription('Replace unrar __builtin_cpu_supports with __get_cpuid for zig-cc static link compatibility')]
    public function patchUnrarCpuDetectionForZig(): void
    {
        SourcePatcher::patchFile('rar_unrar_get_cpuid.patch', $this->getBuildDir());
    }

    #[BeforeStage('php', [php::class, 'makeForWindows'], 'ext-rar')]
    #[PatchDescription('Keep the PHP Windows SDK macros and hide PARSE_SERVER from unrar headers')]
    public function patchUnrarWindowsVersion(): void
    {
        $file = $this->getBuildDir() . '/unrar/os.hpp';
        if (is_file($file)) {
            // unrar/os.hpp unconditionally changes WINVER/_WIN32_WINNT to Windows XP.
            // PHP's Windows build already defines them for the modern SDK, so keep the
            // values the build already selected (this silences the macro warnings).
            FileSystem::replaceFileRegex(
                $file,
                '/^#define WINVER _WIN32_WINNT_WINXP\r?\n#define _WIN32_WINNT _WIN32_WINNT_WINXP/m',
                "#ifndef WINVER\n#define WINVER _WIN32_WINNT_WINXP\n#endif\n"
                . "#ifndef _WIN32_WINNT\n#define _WIN32_WINNT _WIN32_WINNT_WINXP\n#endif"
            );
        }

        // php_variables.h defines PARSE_SERVER as 5. unrar's os.hpp includes
        // shlwapi.h, which includes objbase.h -> urlmon.h, whose PARSEACTION enum
        // also has a PARSE_SERVER member. The macro turns that member into
        // "5 = (PARSE_MIME + 1)" and breaks main/internal_functions.c on MSVC.
        $header = $this->getBuildDir() . '/php_rar.h';
        if (!is_file($header)) {
            return;
        }
        $content = FileSystem::readFile($header);
        if (str_contains($content, 'SPC_RAR_PARSE_SERVER_UNDEF')) {
            return;
        }
        $content = str_replace(
            '#include "unrar/os.hpp"',
            "/* SPC_RAR_PARSE_SERVER_UNDEF: urlmon.h (via shlwapi.h -> objbase.h) declares\n"
            . " * PARSE_SERVER, but php_variables.h defines it as a macro. */\n"
            . "#ifdef PARSE_SERVER\n#undef PARSE_SERVER\n#endif\n"
            . '#include "unrar/os.hpp"',
            $content
        );
        FileSystem::writeFile($header, $content);
    }
}
