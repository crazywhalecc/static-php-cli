<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\store\SourcePatcher;

/**
 * Two-call PGO driver: --pgi instruments, --pgo uses the .profraw the user
 * collected by running the instrumented binaries.
 */
class PgoManager
{
    public const MODE_INSTRUMENT = 'instrument';

    public const MODE_USE = 'use';

    private const TRAINABLE = [
        'cli' => BUILD_TARGET_CLI,
        'micro' => BUILD_TARGET_MICRO,
        'cgi' => BUILD_TARGET_CGI,
        'fpm' => BUILD_TARGET_FPM,
        'embed' => BUILD_TARGET_EMBED,
        'frankenphp' => BUILD_TARGET_FRANKENPHP,
    ];

    /**
     * Applied during --pgi only: explicit __llvm_profile_write_file() at
     * shutdown, since Go/frankenphp exits skip libc atexit.
     */
    private const SHUTDOWN_PATCHES = [
        'php-src' => 'spc_pgo_flush_php_main.patch',
        'frankenphp' => 'spc_pgo_flush_frankenphp.patch',
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
        $this->applyShutdownPatches();
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

    public function applyForSapi(string $sapi): void
    {
        $sapi = $this->resolveSapi($sapi);
        if (!isset(self::TRAINABLE[$sapi])) {
            return;
        }
        if ($this->mode === self::MODE_USE && !is_file($this->profDataFile($sapi))) {
            logger()->warning("pgo --pgo: no profdata for {$sapi}, building without PGO for this sapi");
            $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS', '');
            $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM', '');
            return;
        }
        $flags = $this->mode === self::MODE_INSTRUMENT
            ? '-fprofile-generate=' . $this->rawDir($sapi)
            : '-fprofile-use=' . $this->profDataFile($sapi)
                . ' -Wno-error=profile-instr-unprofiled'
                . ' -Wno-error=profile-instr-out-of-date'
                . ' -Wno-backend-plugin';
        $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS', $flags);
        $this->setFlag('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS_PROGRAM', $this->ldOnly($flags, $sapi));
        logger()->info("pgo {$this->mode} ({$sapi})");
    }

    /**
     * Static-embed mode links libphp.a into frankenphp; both end up in one
     * binary so must share one profdata. Shared-embed mode keeps libphp.so
     * standalone — embed and frankenphp keep separate profiles.
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
            if ($sapi === 'frankenphp') {
                logger()->warning('pgo --pgo: no .profraw for frankenphp (cgo glue PGO will be skipped); run --pgi, exercise frankenphp longer, then re-run --pgo to include it');
                return;
            }
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
        $cur = preg_replace('/\s*-Wno-error=profile-instr-\S+/', '', $cur) ?? $cur;
        $cur = preg_replace('/\s*-Wno-backend-plugin/', '', $cur) ?? $cur;
        f_putenv($var . '=' . trim($cur . ' ' . $append));
    }

    /**
     * Linker flags: cli wants -fprofile-use= at link too (LTO does its
     * profile-driven inlining/reordering at link time). Strip -Wno-error
     * flags (linker doesn't accept them).
     */
    private function ldOnly(string $flags, string $sapi = ''): string
    {
        $patterns = ['/\s*-Wno-error=\S+/', '/\s*-Wno-backend-plugin/'];
        if ($sapi === 'frankenphp') {
            $patterns[] = '/\s*-fprofile-use=\S+/';
        }
        return trim(preg_replace($patterns, '', $flags) ?? $flags);
    }

    /** --pgi patch: inject __llvm_profile_write_file() flush handler to php and frankenphp sources. */
    private function applyShutdownPatches(): void
    {
        $applied = [];
        foreach (self::SHUTDOWN_PATCHES as $dir => $patch) {
            $cwd = SOURCE_PATH . '/' . $dir;
            if (!is_dir($cwd)) {
                continue;
            }
            if (!SourcePatcher::patchFile($patch, $cwd)) {
                throw new WrongUsageException("--pgi: patch {$patch} failed to apply in {$cwd}");
            }
            $applied[] = ['cwd' => $cwd, 'patch' => $patch];
            logger()->info("pgo --pgi: applied {$patch}");
        }
        if ($applied === []) {
            return;
        }
        register_shutdown_function(static function () use ($applied): void {
            foreach ($applied as $entry) {
                $cwd = $entry['cwd'];
                $patch = $entry['patch'];
                if (!is_dir($cwd)) {
                    continue;
                }
                $patch_file = ROOT_DIR . "/src/globals/patch/{$patch}";
                if (!is_file($patch_file)) {
                    continue;
                }
                $args = ' -p1 -s -R -F0 ';
                exec('cd ' . escapeshellarg($cwd) . ' && patch --dry-run' . $args
                    . ' < ' . escapeshellarg($patch_file) . ' >/dev/null 2>&1', $_, $detect_status);
                if ($detect_status !== 0) {
                    logger()->info("pgo --pgi: {$patch} already clean, skipping revert");
                    continue;
                }
                exec('cd ' . escapeshellarg($cwd) . ' && patch' . $args
                    . ' < ' . escapeshellarg($patch_file), $out, $apply_status);
                if ($apply_status === 0) {
                    logger()->info("pgo --pgi: reverted {$patch}");
                } else {
                    logger()->warning("pgo --pgi: failed to revert {$patch} (status {$apply_status}): " . implode("\n", $out));
                }
            }
        });
    }
}
