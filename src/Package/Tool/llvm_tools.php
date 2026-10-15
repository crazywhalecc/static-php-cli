<?php

declare(strict_types=1);

namespace Package\Tool;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\ArtifactExtractor;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Tool;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\BuildFailureException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\ToolPackage;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Util\FileSystem;

#[Tool('llvm-tools')]
class llvm_tools
{
    #[BuildFor('Linux')]
    public function build(ToolPackage $package): void
    {
        $tools = $package->getProvides();
        $concurrency = ApplicationContext::get(PackageBuilder::class)->concurrency;
        $zlib = $this->buildPrivateZlib($package);

        UnixCMakeExecutor::create($package)
            ->setCustomDefaultArgs(
                '-DCMAKE_BUILD_TYPE=Release',
                '-DLLVM_TARGETS_TO_BUILD=',
                '-DLLVM_INCLUDE_BENCHMARKS=OFF',
                '-DLLVM_INCLUDE_TESTS=OFF',
                '-DLLVM_INCLUDE_EXAMPLES=OFF',
                '-DLLVM_INCLUDE_DOCS=OFF',
                '-DLLVM_ENABLE_ZLIB=FORCE_ON',
                "-DZLIB_INCLUDE_DIR={$zlib}/include",
                "-DZLIB_LIBRARY={$zlib}/lib/libz.a",
                '-DLLVM_ENABLE_ZSTD=OFF',
                '-DLLVM_ENABLE_LIBXML2=OFF',
                '-DLLVM_ENABLE_TERMINFO=OFF',
                '-DLLVM_ENABLE_LIBEDIT=OFF',
                '-DLLVM_ENABLE_LIBPFM=OFF',
                '-DLLVM_BUILD_LLVM_DYLIB=OFF',
                '-DLLVM_LINK_LLVM_DYLIB=OFF',
                '-DBUILD_SHARED_LIBS=OFF',
            )
            ->toStep(1)
            ->build('../llvm')
            ->exec("cmake --build . -j {$concurrency} " . implode(' ', array_map(fn ($tool) => "--target {$tool}", $tools)));

        $build_bin = "{$package->getSourceRoot()}/build/bin";
        FileSystem::createDir($package->getBinDir());
        foreach ($tools as $tool) {
            $built = "{$build_bin}/{$tool}";
            if (!is_file($built)) {
                throw new BuildFailureException("llvm-tools: missing build output {$built}");
            }
            FileSystem::copy($built, $package->getBinary($tool));
            chmod($package->getBinary($tool), 0755);
            shell()->exec(escapeshellarg("{$build_bin}/llvm-strip") . ' --strip-all ' . escapeshellarg($package->getBinary($tool)));
        }
    }

    private function buildPrivateZlib(ToolPackage $package): string
    {
        $source = $this->fetchZlibSource();
        $prefix = "{$package->getSourceRoot()}/zlib-prefix";

        $obj_dir = "{$prefix}/obj";
        FileSystem::resetDir($obj_dir);
        FileSystem::createDir("{$prefix}/lib");
        FileSystem::createDir("{$prefix}/include");
        $units = ['adler32', 'crc32', 'deflate', 'infback', 'inffast', 'inflate', 'inftrees', 'trees', 'zutil',
            'compress', 'uncompr', 'gzclose', 'gzlib', 'gzread', 'gzwrite'];
        $sources = implode(' ', array_map(fn ($unit) => escapeshellarg("{$source}/{$unit}.c"), $units));
        $cc = getenv('CC') ?: 'cc';
        $ar = getenv('AR') ?: 'ar';
        shell()->cd($obj_dir)
            ->exec("{$cc} -c -O2 -fPIC -DHAVE_HIDDEN -I" . escapeshellarg($source) . " {$sources}")
            ->exec("{$ar} rcs " . escapeshellarg("{$prefix}/lib/libz.a") . ' *.o');

        foreach (['zlib.h', 'zconf.h'] as $header) {
            FileSystem::copy("{$source}/{$header}", "{$prefix}/include/{$header}");
        }
        return $prefix;
    }

    private function fetchZlibSource(): string
    {
        $artifact = PackageLoader::getPackage('zlib')->getArtifact()
            ?? throw new BuildFailureException('llvm-tools: the zlib package has no artifact to take the source from');
        new ArtifactDownloader(['source-only' => true], interactive: false)->add($artifact)->download();
        new ArtifactExtractor(ApplicationContext::get(ArtifactCache::class))->extract($artifact, force_source: true);

        $source = $artifact->getSourceDir();
        if (!is_file("{$source}/zlib.h")) {
            throw new BuildFailureException("llvm-tools: zlib source not found at {$source}");
        }
        return $source;
    }
}
