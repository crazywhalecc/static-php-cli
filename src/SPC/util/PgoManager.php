<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;

/**
 * Two-call PGO driver: --pgi instruments, --pgo uses the .profraw the user
 * collected by running the instrumented binaries. PgoManager only sets the
 * compiler flags; it does not run any workload itself.
 */
class PgoManager
{
    public const MODE_INSTRUMENT = 'instrument';

    public const MODE_USE = 'use';

    /**
     * SAPIs whose clang-compiled output can be PGO'd. frankenphp is included
     * because its cgo glue is C compiled by zig — the Go side it wraps is
     * not clang-PGO'd here. libphp.so is the embed SAPI; running frankenphp
     * produces profile data for embed (because it loads libphp.so) AND for
     * frankenphp (because the cgo glue runs too).
     */
    private const TRAINABLE = [
        'cli' => BUILD_TARGET_CLI,
        'micro' => BUILD_TARGET_MICRO,
        'cgi' => BUILD_TARGET_CGI,
        'fpm' => BUILD_TARGET_FPM,
        'embed' => BUILD_TARGET_EMBED,
        'frankenphp' => BUILD_TARGET_FRANKENPHP,
    ];

    private string $profileRoot;

    private string $mode;

    private static ?self $active = null;

    public function __construct()
    {
        $this->profileRoot = BUILD_ROOT_PATH . '/pgo-data';
    }

    public static function active(): ?self
    {
        return self::$active;
    }

    /** Setup --pgi: build with -fprofile-generate=<sapi-dir>. */
    public function setupInstrument(int $rule): void
    {
        $this->validateRule($rule);
        FileSystem::removeDir($this->profileRoot);
        f_mkdir($this->profileRoot, recursive: true);
        foreach ($this->trainableIn($rule) as $sapi) {
            f_mkdir($this->rawDir($sapi), recursive: true);
        }
        $this->mode = self::MODE_INSTRUMENT;
        self::$active = $this;
        $this->applyForSapi($this->trainableIn($rule)[0]);
        logger()->info('pgo --pgi: instrumented build, profraw will land under ' . $this->profileRoot . '/<sapi>/');
    }

    /** Setup --pgo: merge collected .profraw, then build with -fprofile-use=<sapi.profdata>. */
    public function setupUse(int $rule): void
    {
        $this->validateRule($rule);
        if (trim((string) shell_exec('command -v llvm-profdata 2>/dev/null')) === '') {
            throw new WrongUsageException('--pgo: llvm-profdata not on PATH');
        }
        foreach ($this->trainableIn($rule) as $sapi) {
            $this->mergeSapi($sapi);
        }
        $this->mode = self::MODE_USE;
        self::$active = $this;
        $this->applyForSapi($this->trainableIn($rule)[0]);
    }

    /**
     * Set EXTRA_CFLAGS / EXTRA_LDFLAGS_PROGRAM for the SAPI about to be built.
     * Non-trainable SAPIs (e.g. frankenphp's Go side) are left untouched.
     */
    public function applyForSapi(string $sapi): void
    {
        $sapi = $this->resolveSapi($sapi);
        if (!isset(self::TRAINABLE[$sapi])) {
            return;
        }
        $flags = $this->mode === self::MODE_INSTRUMENT
            ? '-fprofile-generate=' . $this->rawDir($sapi) . ' -fprofile-continuous -mllvm -disable-vp'
            : '-fprofile-use=' . $this->profDataFile($sapi) . ' -Wno-error=profile-instr-unprofiled -Wno-error=profile-instr-out-of-date -Wno-backend-plugin';
        $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS', $flags);
        $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM', $this->ldOnly($flags));
        logger()->info("pgo {$this->mode} ({$sapi})");
    }

    /**
     * In static-embed mode libphp.a is linked into frankenphp, and the linker
     * resolves all `__llvm_profile_filename` references to a single path —
     * the embed SAPI's per-TU `-fprofile-generate=…` setting is silently
     * dropped. Compile libphp.a with frankenphp's path so all counter writes
     * agree on one file, and read libphp.a's PGO from frankenphp.profdata.
     */
    private function resolveSapi(string $sapi): string
    {
        if ($sapi === 'embed' && getenv('SPC_CMD_VAR_PHP_EMBED_TYPE') === 'static') {
            return 'frankenphp';
        }
        return $sapi;
    }

    private function validateRule(int $rule): void
    {
        if (empty($this->trainableIn($rule))) {
            throw new WrongUsageException('--pgi/--pgo: no trainable SAPI in build rule (need one of: ' . implode(', ', array_keys(self::TRAINABLE)) . ')');
        }
    }

    private function mergeSapi(string $sapi): void
    {
        $raws = glob($this->rawDir($sapi) . '/*.profraw') ?: [];
        if (empty($raws)) {
            throw new WrongUsageException("--pgo: no .profraw for {$sapi}; run --pgi, exercise the binary, then re-run --pgo");
        }
        $out = $this->profDataFile($sapi);
        $argv = implode(' ', array_map('escapeshellarg', $raws));
        shell()->exec('llvm-profdata merge --failure-mode=warn -output=' . escapeshellarg($out) . ' ' . $argv);
        if (!is_file($out) || filesize($out) === 0) {
            throw new WrongUsageException("--pgo: empty merge output for {$sapi}");
        }
        logger()->info("pgo merged {$sapi}: " . filesize($out) . ' bytes');
    }

    private function rawDir(string $sapi): string
    {
        return $this->profileRoot . '/' . $sapi;
    }

    private function profDataFile(string $sapi): string
    {
        return $this->profileRoot . '/' . $sapi . '.profdata';
    }

    /** @return list<string> */
    private function trainableIn(int $rule): array
    {
        $out = [];
        foreach (self::TRAINABLE as $sapi => $mask) {
            if (($rule & $mask) !== $mask) {
                continue;
            }
            $resolved = $this->resolveSapi($sapi);
            if (!in_array($resolved, $out, true)) {
                $out[] = $resolved;
            }
        }
        return $out;
    }

    /** Strip the previous PGO flags from $var and append the new ones. */
    private function setFlag(string $var, string $append): void
    {
        $cur = (string) getenv($var);
        $cur = preg_replace('/\s*-fprofile-(generate|use)=\S+/', '', $cur) ?? $cur;
        $cur = str_replace([' -fprofile-continuous', ' -mllvm -disable-vp'], '', $cur);
        $cur = preg_replace('/\s*-Wno-error=profile-instr-unprofiled\s+-Wno-error=profile-instr-out-of-date\s+-Wno-backend-plugin/', '', $cur) ?? $cur;
        f_putenv($var . '=' . trim($cur . ' ' . $append));
    }

    /** Linker only takes -fprofile-{generate,use}; strip the codegen-only -mllvm and warning flags. */
    private function ldOnly(string $flags): string
    {
        return preg_replace(['/\s*-mllvm\s+\S+/', '/\s*-Wno-error=\S+/', '/\s*-Wno-backend-plugin/'], '', $flags) ?? $flags;
    }
}
