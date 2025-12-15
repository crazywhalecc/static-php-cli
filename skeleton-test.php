<?php

use StaticPHP\Skeleton\ArtifactGenerator;
use StaticPHP\Skeleton\PackageGenerator;


require_once 'vendor/autoload.php';

$package_generator = new PackageGenerator('foo', 'library')
    ->addDependency('bar')
    ->addStaticLib('libfoo.a', 'unix')
    ->addStaticLib('libfoo.a', 'unix')
    ->addArtifact($artifact_generator = new ArtifactGenerator('foo')->setSource(['type' => 'url', 'url' => 'https://example.com/foo.tar.gz']));

$pkg_config = $package_generator->generateConfig();
$artifact_config = $artifact_generator->generateConfig();

echo "===== pkg.json =====" . PHP_EOL;
echo json_encode($pkg_config, 64|128|256) . PHP_EOL;
echo "===== artifact.json =====" . PHP_EOL;
echo json_encode($artifact_config, 64|128|256) . PHP_EOL;
