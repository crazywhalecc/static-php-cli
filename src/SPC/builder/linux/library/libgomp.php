<?php

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\builder\linux\LinuxBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\store\FileSystem;
use SPC\store\source\GccSource;

class libgomp extends LinuxLibraryBase
{
    public const NAME = 'libgomp';

    protected array $static_libs = ['libgomp.a'];

    protected array $headers = [
        'omp.h',
        'openacc.h',
        'acc_prof.h',
    ];

    public function __construct(LinuxBuilder $builder)
    {
        parent::__construct($builder);
        $this->source_dir = SOURCE_PATH . '/gcc';
    }

    /**
     * @throws RuntimeException
     * @throws FileSystemException
     */
    protected function build(): void
    {
        if (getenv('SPC_LIBC') !== 'glibc') {
            return; // can use musl's libgomp.a as it's built with -fPIC
        }
        $outdir = $this->source_dir . '/libgomp/build/out';

        FileSystem::createDir($this->source_dir . '/libgomp/build');
        shell()->cd($this->source_dir . '/libgomp/build')
            ->setEnv([
                'CFLAGS' => $this->getLibExtraCFlags(),
                'LDFLAGS' => $this->getLibExtraLdFlags(),
                'LIBS' => $this->getLibExtraLibs(),
            ])
            ->execWithEnv('../configure --enable-static --disable-shared --prefix= --disable-multilib --with-pic --libdir=/lib --includedir=/include')
            ->execWithEnv('make clean')
            ->execWithEnv("make -j{$this->builder->concurrency}")
            ->execWithEnv("make install DESTDIR={$outdir}");

        copy($outdir . '/lib64/libgomp.a', BUILD_LIB_PATH . '/libgomp.a');
        FileSystem::copyDir(
            $outdir . '/lib/gcc/' . GccSource::getGccVersion() . '/include',
            BUILD_ROOT_PATH
        );
    }
}
