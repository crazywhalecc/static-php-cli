<?php

declare(strict_types=1);

namespace Package\Target;

use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Target;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Package\ToolPackage;
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

    public static function outputs(): array
    {
        return ['libclang_rt.profile.a', 'libclang_rt.cpu_model.a', 'clang_rt.crtbegin.o', 'clang_rt.crtend.o'];
    }

    public static function outputDir(): string
    {
        return getenv('SPC_COMPILER_RT_DIR')
            ?: throw new BuildFailureException('llvm-compiler-rt is only built under the zig toolchain (SPC_COMPILER_RT_DIR is unset)');
    }

    public function isInstalled(): bool
    {
        return self::isBuilt();
    }

    public static function isBuilt(): bool
    {
        $dir = getenv('SPC_COMPILER_RT_DIR');
        return $dir !== false && $dir !== ''
            && array_all(self::outputs(), fn ($file) => file_exists("{$dir}/{$file}"));
    }

    #[BuildFor('Linux')]
    public function build(): void
    {
        $source = $this->getSourceRoot();
        $out = self::outputDir();
        $triple = SystemTarget::getCanonicalTriple();
        FileSystem::createDir($out);

        $this->buildProfileRuntime($source, "{$out}/libclang_rt.profile.a", $triple);
        $this->buildCrtObjects($source, "{$out}/clang_rt.crtbegin.o", "{$out}/clang_rt.crtend.o", $triple);
        $this->buildCpuModel($source, "{$out}/libclang_rt.cpu_model.a", $triple);
    }

    private function buildProfileRuntime(string $source, string $lib, string $triple): void
    {
        $dir = "{$source}/lib/profile";
        if (!is_dir($dir)) {
            throw new BuildFailureException("llvm-compiler-rt: missing profile sources at {$dir}");
        }
        $sources = array_filter(
            [...glob("{$dir}/*.c") ?: [], ...glob("{$dir}/*.cpp") ?: []],
            fn ($file) => !array_any(self::SKIP_PLATFORMS, fn ($skip) => str_contains($file, "/{$skip}")),
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
