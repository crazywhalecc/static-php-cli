<?php

declare(strict_types=1);

namespace SPC\builder;

use SPC\builder\freebsd\BSDBuilder;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\MacOSBuilder;
use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * 用于生成对应系统环境的 Builder 对象的类
 */
class BuilderProvider
{
    private static ?BuilderBase $builder = null;

    /**
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws WrongUsageException
     */
    public static function makeBuilderByInput(InputInterface $input): BuilderBase
    {
        self::$builder = match (PHP_OS_FAMILY) {
            // 'Windows' => new WindowsBuilder(
            //   binary_sdk_dir: $input->getOption('with-sdk-binary-dir'),
            //    vs_ver: $input->getOption('vs-ver'),
            //    arch: $input->getOption('arch'),
            // ),
            'Darwin' => new MacOSBuilder($input->getOptions()),
            'Linux' => new LinuxBuilder($input->getOptions()),
            'BSD' => new BSDBuilder($input->getOptions()),
            default => throw new WrongUsageException('Current OS "' . PHP_OS_FAMILY . '" is not supported yet'),
        };
        return self::$builder;
    }

    /**
     * @throws WrongUsageException
     */
    public static function getBuilder(): BuilderBase
    {
        if (self::$builder === null) {
            throw new WrongUsageException('Builder has not been initialized');
        }
        return self::$builder;
    }
}
