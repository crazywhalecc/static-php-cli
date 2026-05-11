<?php

declare(strict_types=1);

namespace StaticPHP\Util\Pgo;

use StaticPHP\Command\BaseCommand;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Util\FileSystem;
use Symfony\Component\Console\Input\InputInterface;

final class PgoContext
{
    public const string MODE_INSTRUMENT = 'instrument';

    public const string MODE_CS_INSTRUMENT = 'cs-instrument';

    public const string MODE_USE = 'use';

    /**
     * @var array<string, string>
     */
    public const array TRAINABLE = [
        'cli' => 'build-cli',
        'micro' => 'build-micro',
        'cgi' => 'build-cgi',
        'fpm' => 'build-fpm',
        'embed' => 'build-embed',
        'frankenphp' => 'build-frankenphp',
    ];

    public const array SHUTDOWN_PATCHES = [
        'php-src' => 'spc_pgo_flush_php_main.patch',
        'frankenphp' => 'spc_pgo_flush_frankenphp.patch',
    ];

    /** @var list<string> */
    private array $trainableSapis = [];

    public function __construct(
        public readonly string $mode,
        public readonly string $profileRoot,
    ) {
        if (!in_array($mode, [self::MODE_INSTRUMENT, self::MODE_CS_INSTRUMENT, self::MODE_USE], true)) {
            throw new WrongUsageException("PgoContext: unknown mode '{$mode}'");
        }
    }

    public static function registerOptions(BaseCommand $cmd): void
    {
        $cmd->addOption('pgi', null, null, 'PGO instrument pass: build with -fprofile-generate so the resulting binary writes .profraw on shutdown.');
        $cmd->addOption('cs-pgi', null, null, 'PGO context-sensitive instrument pass: -fprofile-use=<existing.profdata> + -fcs-profile-generate. Requires a prior --pgi/--pgo cycle.');
        $cmd->addOption('pgo', null, null, 'PGO use pass: merge the collected .profraw into .profdata, then rebuild with -fprofile-use.');
    }

    /**
     * @param array<string>        $sapis
     * @param array<string, mixed> $build_options
     */
    public static function tryFromInput(InputInterface $input, array $sapis, array &$build_options): ?self
    {
        $modes = array_filter(['pgi', 'cs-pgi', 'pgo'], fn ($m) => (bool) $input->getOption($m));
        if (count($modes) > 1) {
            throw new WrongUsageException('--pgi, --cs-pgi, and --pgo are mutually exclusive');
        }
        $picked = array_values($modes)[0] ?? null;
        if ($picked === null) {
            return null;
        }
        $mode = match ($picked) {
            'pgi' => self::MODE_INSTRUMENT,
            'cs-pgi' => self::MODE_CS_INSTRUMENT,
            'pgo' => self::MODE_USE,
        };
        $ctx = new self($mode, BUILD_ROOT_PATH . '/pgo-data');
        $ctx->setTrainableSapis($sapis);

        match ($mode) {
            self::MODE_INSTRUMENT => $ctx->setupInstrument(),
            self::MODE_CS_INSTRUMENT => $ctx->setupCsInstrument(),
            self::MODE_USE => $ctx->mergeProfiles(),
        };

        if ($ctx->isInstrument() || $ctx->isCsInstrument()) {
            $build_options['no-strip'] = true;
        }
        ApplicationContext::set(self::class, $ctx);
        return $ctx;
    }

    public function isInstrument(): bool
    {
        return $this->mode === self::MODE_INSTRUMENT;
    }

    public function isCsInstrument(): bool
    {
        return $this->mode === self::MODE_CS_INSTRUMENT;
    }

    public function isUse(): bool
    {
        return $this->mode === self::MODE_USE;
    }

    /**
     * @param list<string> $sapis
     */
    public function setTrainableSapis(array $sapis): void
    {
        $resolved = [];
        foreach ($sapis as $sapi) {
            $r = $this->resolveSapi($sapi);
            if (!in_array($r, $resolved, true)) {
                $resolved[] = $r;
            }
        }
        if ($resolved === []) {
            throw new WrongUsageException(
                'PGO: no trainable SAPI selected; supply one of ' . implode(', ', array_keys(self::TRAINABLE))
            );
        }
        $this->trainableSapis = $resolved;
    }

    /** @return list<string> */
    public function trainableSapis(): array
    {
        return $this->trainableSapis;
    }

    /**
     * Static-embed mode links libphp.a into frankenphp, sharing a single binary
     * and profdata. Shared-embed keeps them separate.
     */
    public function resolveSapi(string $sapi): string
    {
        if ($sapi === 'embed' && getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'static') {
            return 'frankenphp';
        }
        return $sapi;
    }

    public function rawDir(string $sapi): string
    {
        return $this->profileRoot . '/' . $sapi;
    }

    public function csRawDir(string $sapi): string
    {
        return $this->profileRoot . '/cs-' . $sapi;
    }

    public function profDataFile(string $sapi): string
    {
        return $this->profileRoot . '/' . $sapi . '.profdata';
    }

    public function cflagsFor(string $sapi): string
    {
        $sapi = $this->resolveSapi($sapi);
        if ($this->mode === self::MODE_USE && !is_file($this->profDataFile($sapi))) {
            return '';
        }
        return match ($this->mode) {
            self::MODE_INSTRUMENT => '-fprofile-generate=' . $this->rawDir($sapi)
                . ' -fprofile-update=atomic',
            self::MODE_CS_INSTRUMENT => '-fprofile-use=' . $this->profDataFile($sapi)
                . ' -fcs-profile-generate=' . $this->csRawDir($sapi)
                . ' -fprofile-update=atomic'
                . ' -Wno-error=profile-instr-unprofiled'
                . ' -Wno-error=profile-instr-out-of-date'
                . ' -Wno-backend-plugin',
            self::MODE_USE => '-fprofile-use=' . $this->profDataFile($sapi)
                . ' -Wno-error=profile-instr-unprofiled'
                . ' -Wno-error=profile-instr-out-of-date'
                . ' -Wno-backend-plugin',
            default => throw new WrongUsageException("PgoContext: unreachable mode '{$this->mode}'"),
        };
    }

    public function ldflagsFor(string $sapi): string
    {
        $resolved = $this->resolveSapi($sapi);
        $flags = $this->cflagsFor($sapi);
        $patterns = ['/\s*-Wno-error=\S+/', '/\s*-Wno-backend-plugin/'];
        if ($resolved === 'frankenphp') {
            $patterns[] = '/\s*-fprofile-use=\S+/';
            $patterns[] = '/\s*-fcs-profile-generate=\S+/';
        }
        return trim((string) preg_replace($patterns, '', $flags));
    }

    public function applyEnvFor(string $sapi): void
    {
        self::overwritePgoFlags('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS', $this->cflagsFor($sapi));
        self::overwritePgoFlags('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM', $this->ldflagsFor($sapi));
    }

    public function setupInstrument(): void
    {
        FileSystem::removeDir($this->profileRoot);
        FileSystem::createDir($this->profileRoot);
        foreach ($this->trainableSapis as $sapi) {
            FileSystem::createDir($this->rawDir($sapi));
        }
    }

    public function setupCsInstrument(): void
    {
        foreach ($this->trainableSapis as $sapi) {
            if (!is_file($this->profDataFile($sapi))) {
                throw new WrongUsageException(
                    "PGO --phase=cs-instrument: missing {$sapi}.profdata; run --phase=instrument and --phase=use first"
                );
            }
            FileSystem::createDir($this->csRawDir($sapi));
        }
    }

    public function mergeProfiles(): void
    {
        if (trim((string) shell_exec('command -v llvm-profdata 2>/dev/null')) === '') {
            throw new WrongUsageException('PGO --phase=use: llvm-profdata not on PATH');
        }
        foreach ($this->trainableSapis as $sapi) {
            $this->mergeSapi($sapi);
        }
    }

    private function mergeSapi(string $sapi): void
    {
        $raws = glob($this->rawDir($sapi) . '/*.profraw') ?: [];
        $csRaws = glob($this->csRawDir($sapi) . '/*.profraw') ?: [];
        if ($raws === [] && $csRaws === []) {
            if ($sapi === 'frankenphp') {
                logger()->warning(
                    'PGO --phase=use: no .profraw for frankenphp (cgo-glue PGO will be skipped); ' .
                    'run --phase=instrument, exercise frankenphp longer, then re-run --phase=use'
                );
                return;
            }
            throw new WrongUsageException(
                "PGO --phase=use: no .profraw for {$sapi}; run --phase=instrument, exercise the binary, then re-run --phase=use"
            );
        }
        $out = $this->profDataFile($sapi);
        $inputs = array_merge($raws, $csRaws);
        $argv = implode(' ', array_map('escapeshellarg', $inputs));
        shell()->exec('llvm-profdata merge --failure-mode=warn -output=' . escapeshellarg($out) . ' ' . $argv);
        if (!is_file($out) || filesize($out) === 0) {
            throw new WrongUsageException("PGO --phase=use: empty merge output for {$sapi}");
        }
        logger()->info("PGO merged {$sapi}: " . filesize($out) . ' bytes');
    }

    private static function overwritePgoFlags(string $var, string $append): void
    {
        $cur = (string) getenv($var);
        $cur = preg_replace('/\s*-f(cs-)?profile-(generate|use)=\S+/', '', $cur) ?? $cur;
        $cur = preg_replace('/\s*-fprofile-update=atomic/', '', $cur) ?? $cur;
        $cur = preg_replace('/\s*-Wno-error=profile-instr-\S+/', '', $cur) ?? $cur;
        $cur = preg_replace('/\s*-Wno-backend-plugin/', '', $cur) ?? $cur;
        f_putenv($var . '=' . trim(trim($cur) . ' ' . $append));
    }
}
