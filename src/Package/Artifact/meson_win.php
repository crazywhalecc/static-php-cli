<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Util\FileSystem;

class meson_win
{
    /**
     * Meson 1.11 dropped the Windows MSI, so the artifact is the wheel, installed offline
     * into a private venv. The venv is not just packaging hygiene: pip generates a real
     * meson.exe launcher there, and PostgreSQL needs one because its build re-invokes meson
     * through find_program(), which rejects non-executables like the meson.pyz zipapp.
     */
    #[AfterBinaryExtract('meson', ['windows-x86_64'])]
    public function afterExtract(string $target_path): void
    {
        $dir = dirname($target_path);
        $venv = "{$dir}\\venv";

        // Prefer the python-win tool package (installed alongside via tools@windows),
        // fall back to whatever Python the machine already has.
        $candidates = ['"' . PKG_ROOT_PATH . '\python-win\tools\python.exe"', 'python', 'py -3'];
        $python = null;
        foreach ($candidates as $candidate) {
            [$code] = cmd()->execWithResult("{$candidate} --version", false);
            if ($code === 0) {
                $python = $candidate;
                break;
            }
        }
        if ($python === null) {
            throw new EnvironmentException('meson needs Python 3; the python-win tool package did not install and no system Python was found.');
        }

        if (is_dir($venv)) {
            FileSystem::removeDir($venv);
        }
        cmd()->exec("{$python} -m venv \"{$venv}\"")
            ->exec("\"{$venv}\\Scripts\\python.exe\" -m pip install --no-index --no-deps \"{$target_path}\"");

        FileSystem::writeFile("{$dir}\\meson.bat", "@echo off\r\n\"%~dp0venv\\Scripts\\meson.exe\" %*\r\n");
    }
}
