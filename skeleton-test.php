<?php

declare(strict_types=1);

use StaticPHP\Runtime\Executor\UnixCMakeExecutor;
use StaticPHP\Skeleton\ArtifactGenerator;
use StaticPHP\Skeleton\ExecutorGenerator;
use StaticPHP\Skeleton\PackageGenerator;

require_once 'vendor/autoload.php';

$package_generator = new PackageGenerator('foo', 'library')
    ->addDependency('bar')
    ->addStaticLib('libfoo.a', 'unix')
    ->addStaticLib('libfoo.a', 'unix')
    ->addArtifact($artifact_generator = new ArtifactGenerator('foo')->setSource(['type' => 'url', 'url' => 'https://example.com/foo.tar.gz']))
    ->enableBuild(['Darwin', 'Linux'], 'build')
    ->addFunctionExecutorBinding('build', new ExecutorGenerator(UnixCMakeExecutor::class));

$pkg_config = $package_generator->generateConfigArray();
$artifact_config = $artifact_generator->generateConfigArray();

echo '===== pkg.json =====' . PHP_EOL;
echo json_encode($pkg_config, 64 | 128 | 256) . PHP_EOL;
echo '===== artifact.json =====' . PHP_EOL;
echo json_encode($artifact_config, 64 | 128 | 256) . PHP_EOL;
echo '===== php code for package =====' . PHP_EOL;
echo $package_generator->generatePackageClassFile('Package\Library');
