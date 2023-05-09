<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use SPC\exception\WrongUsageException;
use SPC\util\UnixShell;
use ZM\Logger\ConsoleLogger;

/**
 * 判断传入的数组是否为关联数组
 *
 * @param mixed $array
 */
function is_assoc_array($array): bool
{
    return is_array($array) && (!empty($array) && array_keys($array) !== range(0, count($array) - 1));
}

/**
 * 助手方法，返回一个 Logger 实例
 */
function logger(): LoggerInterface
{
    global $ob_logger;
    if ($ob_logger === null) {
        return new ConsoleLogger();
    }
    return $ob_logger;
}

/**
 * @param  string              $arch 架构名称转换为 GNU 标准形式
 * @throws WrongUsageException
 */
function arch2gnu(string $arch): string
{
    $arch = strtolower($arch);
    return match ($arch) {
        'x86_64', 'x64', 'amd64' => 'x86_64',
        'arm64', 'aarch64' => 'aarch64',
        default => throw new WrongUsageException('Not support arch: ' . $arch),
        // 'armv7' => 'arm',
    };
}

function quote(string $str, string $quote = '"'): string
{
    return $quote . $str . $quote;
}

/**
 * 将不同系统环境的编译使用工具集的文件夹名称进行一个返回
 *
 * @throws WrongUsageException
 */
function osfamily2dir(): string
{
    return match (PHP_OS_FAMILY) {
        /* @phpstan-ignore-next-line */
        'Windows', 'WINNT', 'Cygwin' => 'windows',
        'Darwin' => 'macos',
        'Linux' => 'linux',
        default => throw new WrongUsageException('Not support os: ' . PHP_OS_FAMILY),
    };
}

/**
 * 执行shell，直接输出在终端，出现错误抛出异常
 *
 * @throws \SPC\exception\RuntimeException
 */
function f_passthru(string $cmd): ?bool
{
    $danger = false;
    foreach (DANGER_CMD as $danger_cmd) {
        if (str_starts_with($cmd, $danger_cmd . ' ')) {
            $danger = true;
            break;
        }
    }
    if ($danger) {
        logger()->notice('Running dangerous command: ' . $cmd);
    } else {
        logger()->debug('Running command with direct output: ' . $cmd);
    }
    $ret = passthru($cmd, $code);
    if ($code !== 0) {
        throw new \SPC\exception\RuntimeException('Command run failed with code[' . $code . ']: ' . $cmd, $code);
    }
    return $ret;
}

/**
 * 执行命令，不输出内容，返回执行结果和内容
 *
 * @param mixed $output
 * @param mixed $result_code
 */
function f_exec(string $command, &$output, &$result_code): bool|string
{
    logger()->debug('Running command (no output) : ' . $command);
    return exec($command, $output, $result_code);
}

function f_mkdir(string $directory, int $permissions = 0777, bool $recursive = false): bool
{
    if (file_exists($directory)) {
        logger()->debug("Dir {$directory} already exists, ignored");
        return true;
    }
    logger()->debug('Making new directory ' . ($recursive ? 'recursive' : '') . ': ' . $directory);
    return mkdir($directory, $permissions, $recursive);
}

function shell(?bool $debug = null): UnixShell
{
    return new UnixShell($debug);
}
