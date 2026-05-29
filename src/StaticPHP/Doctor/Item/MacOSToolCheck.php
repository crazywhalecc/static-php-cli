<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\System\MacOSUtil;

class MacOSToolCheck
{
    public const array REQUIRED_COMMANDS = [
        'curl',
        'make',
        'bison',
        're2c',
        'flex',
        'gperf',
        'pkg-config',
        'git',
        'autoconf',
        'automake',
        'tar',
        'libtool',
        'unzip',
        'xz',
        'gzip',
        'bzip2',
        'cmake',
        'glibtoolize',
    ];

    #[CheckItem('if homebrew has installed', limit_os: 'Darwin', level: 998)]
    public function checkBrew(): ?CheckResult
    {
        if (($path = MacOSUtil::findCommand('brew')) === null) {
            return CheckResult::fail('Homebrew is not installed', 'brew');
        }
        if ($path !== '/opt/homebrew/bin/brew' && getenv('GNU_ARCH') === 'aarch64') {
            return CheckResult::fail('Current homebrew (/usr/local/bin/homebrew) is not installed for M1 Mac, please re-install homebrew in /opt/homebrew/ !');
        }
        return CheckResult::ok();
    }

    #[CheckItem('if macports has installed', limit_os: 'Darwin', level: 998)]
    public function checkPorts(): ?CheckResult
    {
        if (($path = MacOSUtil::findCommand('port')) === null) {
            return CheckResult::fail('MacPorts is not installed', 'port');
        }
        if ($path !== '/opt/local/bin/port' && getenv('GNU_ARCH') === 'aarch64') {
            return CheckResult::fail('Current macports (/opt/local/bin/port) is not installed for M1 Mac, please re-install macports!');
        }
        return CheckResult::ok();
    }

    #[CheckItem('if necessary tools are installed', limit_os: 'Darwin')]
    public function checkCliTools(): ?CheckResult
    {
        $missing = [];
        foreach (self::REQUIRED_COMMANDS as $cmd) {
            if (MacOSUtil::findCommand($cmd) === null) {
                $missing[] = $cmd;
            }
        }
        if (!empty($missing)) {
            return CheckResult::fail('missing system commands: ' . implode(', ', $missing), 'build-tools', ['missing' => $missing]);
        }
        return CheckResult::ok();
    }

    #[CheckItem('if homebrew llvm are installed', limit_os: 'Darwin')]
    public function checkBrewLLVM(): ?CheckResult
    {
        if (getenv('SPC_USE_LLVM') === 'brew') {
            $homebrew_prefix = getenv('HOMEBREW_PREFIX') ?: (SystemTarget::getTargetArch() === 'aarch64' ? '/opt/homebrew' : '/usr/local/homebrew');

            if (($path = MacOSUtil::findCommand('clang', ["{$homebrew_prefix}/opt/llvm/bin"])) === null) {
                return CheckResult::fail('Homebrew llvm is not installed', 'build-tools', ['missing' => ['llvm']]);
            }
            return CheckResult::ok($path);
        }
        return null;
    }

    #[CheckItem('if macports llvm are installed', limit_os: 'Darwin')]
    public function checkPortsLLVM(): ?CheckResult
    {
        if (getenv('SPC_USE_LLVM') === 'port') {
            $macports_prefix = getenv('MACPORTS_PREFIX') ?: '/opt/local';

            if (($path = MacOSUtil::findCommand('clang', ["{$macports_prefix}/bin"])) === null) {
                return CheckResult::fail('MacPorts llvm is not installed', 'build-tools', ['missing' => ['llvm']]);
            }
            return CheckResult::ok($path);
        }
        return null;
    }

    #[CheckItem('if bison version is 3.0 or later', limit_os: 'Darwin')]
    public function checkBisonVersion(array $command_path = []): ?CheckResult
    {
        // if the bison command is /usr/bin/bison, it is the system bison that may be too old
        if (($bison = MacOSUtil::findCommand('bison', $command_path)) === null) {
            return CheckResult::fail('bison is not installed or too old', 'build-tools', ['missing' => ['bison']]);
        }
        // check version: bison (GNU Bison) x.y(.z)
        $version = shell()->execWithResult("{$bison} --version", false);
        if (preg_match('/bison \(GNU Bison\) (\d+)\.(\d+)(?:\.(\d+))?/', $version[1][0], $matches)) {
            $major = (int) $matches[1];
            // major should be 3 or later
            if ($major < 3) {
                // find homebrew keg-only bison
                if ($command_path !== []) {
                    return CheckResult::fail("Current {$bison} version is too old: " . $matches[0]);
                }
                return $this->checkBisonVersion(['/opt/homebrew/opt/bison/bin', '/usr/local/opt/bison/bin', '/opt/local/bin']);
            }
            return CheckResult::ok($matches[0]);
        }
        return CheckResult::fail('bison version cannot be determined');
    }

    #[FixItem('brew')]
    public function fixBrew(): bool
    {
        shell(true)->exec('/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"');
        return true;
    }

    #[FixItem('build-tools')]
    public function fixBuildTools(array $missing): bool
    {
        $hasBrew = $this->checkBrew()?->isOK();
        $hasMacports = $this->checkPorts()?->isOK();

        $replacement = [
            'glibtoolize' => 'libtool',
        ];
        foreach ($missing as $cmd) {
            if (isset($replacement[$cmd])) {
                $cmd = $replacement[$cmd];
            }

            if ($hasBrew) {
                shell()->exec('brew install --formula ' . escapeshellarg($cmd));
                continue;
            }

            if ($hasMacports) {
                shell()->exec('port install ' . escapeshellarg($cmd));
                continue;
            }

            return false;
        }
        return true;
    }
}
