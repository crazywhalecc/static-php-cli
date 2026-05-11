<?php

declare(strict_types=1);

namespace Package\Target\php;

use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\ConditionalOn;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Util\Pgo\PgoContext;
use StaticPHP\Util\SourcePatcher;

trait pgo
{
    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'buildconfForUnix'], 'php')]
    #[PatchDescription('Inject __llvm_profile_write_file() flush at php/frankenphp shutdown for instrumented builds')]
    public function pgoApplyShutdownPatches(PgoContext $pgo): void
    {
        if (!$pgo->isInstrument() && !$pgo->isCsInstrument()) {
            return;
        }
        foreach (PgoContext::SHUTDOWN_PATCHES as $dir => $patch) {
            $cwd = SOURCE_PATH . '/' . $dir;
            if (!is_dir($cwd)) {
                continue;
            }
            if (!SourcePatcher::patchFile($patch, $cwd)) {
                throw new WrongUsageException("PGO --phase=instrument: patch {$patch} failed to apply in {$cwd}");
            }
            logger()->info("PGO --phase=instrument: applied {$patch}");
        }
    }

    #[ConditionalOn(PgoContext::class)]
    #[AfterStage('php', [self::class, 'configureForUnix'], 'php')]
    #[PatchDescription('Patch libtool to passthrough -fcs-profile-* for context-sensitive PGO')]
    public function pgoPatchLibtoolForCsInstrument(PgoContext $pgo): void
    {
        if (!$pgo->isCsInstrument()) {
            return;
        }
        $libtool = SOURCE_PATH . '/php-src/libtool';
        if (!is_file($libtool)) {
            return;
        }
        $contents = (string) file_get_contents($libtool);
        if (str_contains($contents, '-fcs-profile-*')) {
            return;
        }
        $patched = str_replace('-fprofile-*|-F*', '-fprofile-*|-fcs-profile-*|-F*', $contents);
        if ($patched === $contents) {
            logger()->warning('PGO --phase=cs-instrument: could not patch libtool for -fcs-profile-* passthrough');
            return;
        }
        file_put_contents($libtool, $patched);
        logger()->info('PGO --phase=cs-instrument: patched libtool for -fcs-profile-* passthrough');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'configureForUnix'], 'php')]
    public function pgoApplyConfigureFlags(PgoContext $pgo): void
    {
        $sapis = $pgo->trainableSapis();
        if ($sapis === []) {
            return;
        }
        $pgo->applyEnvFor($sapis[0]);
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'makeCliForUnix'], 'php')]
    public function pgoBeforeMakeCli(PgoContext $pgo, TargetPackage $package): void
    {
        $this->pgoBeforeSapiMake($pgo, $package, 'cli');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'makeCgiForUnix'], 'php')]
    public function pgoBeforeMakeCgi(PgoContext $pgo, TargetPackage $package): void
    {
        $this->pgoBeforeSapiMake($pgo, $package, 'cgi');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'makeFpmForUnix'], 'php')]
    public function pgoBeforeMakeFpm(PgoContext $pgo, TargetPackage $package): void
    {
        $this->pgoBeforeSapiMake($pgo, $package, 'fpm');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'makeMicroForUnix'], 'php')]
    public function pgoBeforeMakeMicro(PgoContext $pgo, TargetPackage $package): void
    {
        $this->pgoBeforeSapiMake($pgo, $package, 'micro');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'makeEmbedForUnix'], 'php')]
    public function pgoBeforeMakeEmbed(PgoContext $pgo, TargetPackage $package): void
    {
        $this->pgoBeforeSapiMake($pgo, $package, 'embed');
    }

    #[ConditionalOn(PgoContext::class)]
    #[BeforeStage('php', [self::class, 'buildFrankenphpForUnix'], 'php')]
    public function pgoBeforeBuildFrankenphp(PgoContext $pgo): void
    {
        $pgo->applyEnvFor('frankenphp');
        logger()->info("PGO {$pgo->mode}: applying flags for frankenphp");
    }

    private function pgoBeforeSapiMake(PgoContext $pgo, TargetPackage $package, string $sapi): void
    {
        $resolved = $pgo->resolveSapi($sapi);
        if (!in_array($resolved, $pgo->trainableSapis(), true)) {
            return;
        }
        shell()->cd($package->getSourceDir())->exec('make clean');
        $pgo->applyEnvFor($sapi);
        logger()->info("PGO {$pgo->mode}: applying flags for {$sapi}");
    }
}
