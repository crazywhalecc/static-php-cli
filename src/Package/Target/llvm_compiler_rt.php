<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Package\InstallStamp;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Package\ToolPackage;
use StaticPHP\Package\ToolVersionRegistry;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

/**
 * Builds the three compiler-rt pieces zig ships without, into zig/lib/{triple}:
 *
 *  - libclang_rt.profile.a      the PGO instrumentation runtime. Without it
 *                               -fprofile-instr-generate still links, but writes no .profraw.
 *                               https://codeberg.org/ziglang/zig/issues/32066
 *  - clang_rt.crtbegin/crtend.o __dso_handle, needed when linking shared libraries.
 *                               https://codeberg.org/ziglang/zig/issues/32064
 *  - libclang_rt.cpu_model.a    __cpu_model and __cpu_indicator_init, the globals
 *                               __builtin_cpu_supports() and __builtin_cpu_init() resolve against.
 *
 * zig-cc.sh links whichever of them a given command needs, from SPC_COMPILER_RT_DIR.
 */
#[Target('llvm-compiler-rt')]
class llvm_compiler_rt extends TargetPackage
{
    private const array SKIP_PLATFORMS = ['PlatformAIX', 'PlatformDarwin', 'PlatformFuchsia', 'PlatformOther', 'PlatformWindows', 'WindowsMMap'];

    /** The objects this package installs, relative to getRuntimeDir(). */
    private const array OUTPUT_FILES = ['libclang_rt.profile.a', 'libclang_rt.cpu_model.a', 'clang_rt.crtbegin.o', 'clang_rt.crtend.o'];

    /**
     * @return string[]
     */
    public function getInstallFiles(): array
    {
        return array_map(fn (string $file): string => $this->getRuntimeDir() . '/' . $file, self::OUTPUT_FILES);
    }

    #[BuildFor('Linux')]
    public function build(): void
    {
        $source = $this->getSourceRoot();
        $out = $this->getRuntimeDir();
        $triple = SystemTarget::getCanonicalTriple();
        FileSystem::createDir($out);

        $this->buildProfileRuntime($source, "{$out}/libclang_rt.profile.a", $triple);
        $this->buildCrtObjects($source, "{$out}/clang_rt.crtbegin.o", "{$out}/clang_rt.crtend.o", $triple);
        $this->buildCpuModel($source, "{$out}/libclang_rt.cpu_model.a", $triple);
        // PackageBuilder records the install stamp (the zig version) once the build succeeded.
    }

    /**
     * zig ships exactly one clang and these objects are tied to it, so the installed zig version
     * is their fingerprint: upgrading zig must rebuild them.
     *
     * The version comes from the tool version registry that PackageInstaller writes when the zig
     * package is installed - no `zig cc --version` subprocess, and no chance of a stale memo. The
     * stamp lives next to the objects it describes, so both are replaced together.
     */
    protected function getInstallStamp(): ?InstallStamp
    {
        $version = ToolVersionRegistry::get('zig');
        if ($version === null) {
            // isInstalled() runs often (build gate, doctor, dev commands), so this stays at debug
            // level: without a recorded version the check simply degrades to file existence.
            logger()->debug('Cannot determine the installed zig version; llvm-compiler-rt is only checked for file existence.');
            return null;
        }
        return new InstallStamp($this->getRuntimeDir() . '/' . self::INSTALL_STAMP_FILENAME, $version);
    }

    /**
     * Where the compiler-rt objects live: {pkg_root}/zig/lib/{triple}.
     *
     * ZigToolchain::initEnv() exports SPC_COMPILER_RT_DIR for the active triple, so the directory
     * follows SPC_TARGET; before the toolchain is initialized, fall back to the canonical location.
     */
    protected function getRuntimeDir(): string
    {
        $dir = getenv('SPC_COMPILER_RT_DIR');
        return is_string($dir) && $dir !== ''
            ? $dir
            : PKG_ROOT_PATH . '/zig/lib/' . SystemTarget::getCanonicalTriple();
    }

    private function buildProfileRuntime(string $source, string $lib, string $triple): void
    {
        $dir = "{$source}/lib/profile";
        if (!is_dir($dir)) {
            throw new BuildFailureException("llvm-compiler-rt: missing profile sources at {$dir}");
        }
        $sources = array_filter(
            [...glob("{$dir}/*.c") ?: [], ...glob("{$dir}/*.cpp") ?: []],
            // Skip the platform-specific translation units (InstrProfilingPlatformWindows.c, ...).
            // Match on the basename: the filenames carry an "InstrProfiling" prefix, so a
            // path-segment match like "/PlatformWindows" never fires.
            fn ($file) => !array_any(self::SKIP_PLATFORMS, fn ($skip) => str_contains(basename($file), $skip)),
        );

        $obj_dir = "{$source}/obj-profile-{$triple}";
        FileSystem::resetDir($obj_dir);
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden -I" . escapeshellarg("{$source}/include")
            . ' -DCOMPILER_RT_HAS_ATOMICS=1 -DCOMPILER_RT_HAS_FCNTL_LCK=1 -DCOMPILER_RT_HAS_UNAME=1';
        shell()->cd($obj_dir)
            ->exec($this->zig('cc') . " {$cflags} " . implode(' ', array_map('escapeshellarg', $sources)))
            ->exec($this->zig('ar') . ' rcs ' . escapeshellarg($lib) . ' *.o');
    }

    private function buildCrtObjects(string $source, string $begin, string $end, string $triple): void
    {
        $cflags = "-target {$triple} -c -O2 -fPIC -fvisibility=hidden -DCRT_HAS_INITFINI_ARRAY";
        foreach ([['crtbegin.c', $begin], ['crtend.c', $end]] as [$name, $dst]) {
            $src = "{$source}/lib/builtins/{$name}";
            if (!is_file($src)) {
                throw new BuildFailureException("llvm-compiler-rt: missing {$src}");
            }
            shell()->exec($this->zig('cc') . " {$cflags} -o " . escapeshellarg($dst) . ' ' . escapeshellarg($src));
        }
    }

    private function buildCpuModel(string $source, string $lib, string $triple): void
    {
        $builtins = "{$source}/lib/builtins";
        $arch = explode('-', $triple)[0];
        $family = match (true) {
            in_array($arch, ['x86_64', 'amd64', 'i386', 'i686', 'x86'], true) => 'x86',
            in_array($arch, ['aarch64', 'arm64'], true) => 'aarch64',
            str_starts_with($arch, 'riscv') => 'riscv',
            default => null,
        };
        if ($family === null) {
            logger()->debug("llvm-compiler-rt: no cpu_model implementation for {$triple}, skipping");
            return;
        }
        [$src, $includes] = is_dir("{$builtins}/cpu_model")
            ? ["{$builtins}/cpu_model/{$family}.c", '-I' . escapeshellarg($builtins) . ' -I' . escapeshellarg("{$builtins}/cpu_model")]
            : ["{$builtins}/cpu_model.c", '-I' . escapeshellarg($builtins)];
        if (!is_file($src)) {
            throw new BuildFailureException("llvm-compiler-rt: missing cpu_model source {$src}");
        }

        $obj_dir = "{$source}/obj-cpu-model-{$triple}";
        FileSystem::resetDir($obj_dir);
        $obj = "{$obj_dir}/cpu_model.o";
        shell()
            ->exec($this->zig('cc') . " -target {$triple} -c -O2 -fPIC {$includes} -o " . escapeshellarg($obj) . ' ' . escapeshellarg($src))
            ->exec($this->zig('ar') . ' rcs ' . escapeshellarg($lib) . ' ' . escapeshellarg($obj));
    }

    private function zig(string $subcommand): string
    {
        $zig = PackageLoader::getPackage('zig');
        if (!$zig instanceof ToolPackage) {
            throw new BuildFailureException('llvm-compiler-rt requires the zig tool package');
        }
        return escapeshellarg($zig->getBinary('zig')) . ' ' . $subcommand;
    }
}
