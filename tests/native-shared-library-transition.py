#!/usr/bin/env python3
"""Exercise SPC's installer across a selective native SONAME transition."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


RUNNER = r'''<?php
declare(strict_types=1);

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Registry\PackageLoader;

$autoload = $argv[1] . '/vendor/autoload.php';
require is_file($autoload) ? $autoload : dirname($argv[1], 2) . '/autoload.php';
require $argv[1] . '/src/bootstrap.php';
logger()->setLevel('error');
$names = ['base', 'consumer', 'other'];
$configs = $artifacts = $packages = [];
foreach ($names as $short) {
    $name = 'transition-' . $short;
    $configs[$name] = ['type' => 'library', 'artifact' => $name,
        'shared-libs' => ['libtransition-' . $short . '.so'],
        'depends' => $short === 'consumer' ? ['transition-base'] : [], 'lang' => $short === 'other' ? 'cpp' : 'c'];
    $artifacts[$name] = ['source' => ['type' => 'local'],
        'metadata' => ['license' => 'MIT', 'license-files' => ['LICENSE']]];
    $package = new LibraryPackage($name, 'library');
    $package->addStage('build', function () use ($short, $name, $package): void {
        $cache = json_decode(file_get_contents(DOWNLOAD_PATH . '/.cache.json'), true, 512, JSON_THROW_ON_ERROR);
        $version = $short === 'base' ? $cache[$name]['source']['version'] : '1';
        $soname = 'libtransition-' . $short . '.so.' . $version;
        if ($short === 'base' && $version === '3') {
            symlink('missing-artifact.a', BUILD_LIB_PATH . '/incomplete.a');
        }
        if ($short === 'other') {
            shell(false)->exec(escapeshellarg(getenv('CXX')) . ' -c -fPIC -g -gdwarf-4 '
                . escapeshellarg(WORKING_DIR . '/native-sources/' . $name . '/fixture.c')
                . ' -o ' . escapeshellarg(BUILD_LIB_PATH . '/other.o'));
            shell(false)->exec('ar rcs ' . escapeshellarg(BUILD_LIB_PATH . '/libtransition-other.a')
                . ' ' . escapeshellarg(BUILD_LIB_PATH . '/other.o'));
            StaticPHP\Util\SharedLibraryUtil::linkArchive($package, 'libtransition-other.a');
            unlink(BUILD_LIB_PATH . '/other.o');
            StaticPHP\Util\FileSystem::removeDir($package->getSourceDir());
            file_put_contents(WORKING_DIR . '/compiled.jsonl', json_encode($short) . "\n", FILE_APPEND);
            return;
        }

        $command = [$short === 'other' ? getenv('CXX') : getenv('CC'), '-shared', '-fPIC', '-g', '-gdwarf-4', '-Wl,-soname,' . $soname,
            '-o', BUILD_LIB_PATH . '/' . $soname, WORKING_DIR . '/native-sources/' . $name . '/fixture.c'];
        if ($short === 'consumer') {
            $command[] = '-L' . BUILD_LIB_PATH;
            $command[] = '-ltransition-base';
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Native fixture compile failed: ' . $output);
        }
        symlink($soname, BUILD_LIB_PATH . '/libtransition-' . $short . '.so');
        file_put_contents(WORKING_DIR . '/compiled.jsonl', json_encode($short) . "\n", FILE_APPEND);
    });
    $packages[$name] = $package;
}
(new ReflectionProperty(PackageConfig::class, 'package_configs'))->setValue(null, $configs);
ArtifactConfig::_restoreFromCache($artifacts);
(new ReflectionProperty(PackageLoader::class, 'packages'))->setValue(null, $packages);
ApplicationContext::set(ArtifactCache::class, new ArtifactCache());
$options = ['with-shared-libs' => true, 'with-shared-libdir' => '/usr/lib/php-zts/lib',
    'no-download' => true, 'no-tracker' => true, 'reuse-installed-library-dependencies' => true];
if ($argv[2] === 'reuse') {
    $options['use-existing-libraries'] = true;
}
$installer = new PackageInstaller($options, false);
foreach (explode(',', $argv[3]) as $short) {
    if ($argv[2] === 'reuse') {
        $installer->addInstallPackage($packages['transition-' . $short]);
    } else {
        $installer->addBuildPackage($packages['transition-' . $short]);
    }
}
$installer->run(true);
echo file_get_contents(BUILD_ROOT_PATH . '/shared-libraries.json');
'''


class InstallerTransitionTests(unittest.TestCase):
    def test_native_soname_transition_runs_root_then_consumer_in_separate_installers(self):
        if not all(shutil.which(command) for command in ['php', 'cc', 'g++', 'ar', 'patchelf', 'readelf', 'ldd']):
            self.skipTest('Native fixture requires PHP, C/C++ compilers, patchelf and readelf')
        with tempfile.TemporaryDirectory(prefix='spc-installer-transition-') as temporary:
            root = Path(temporary)
            (root / 'downloads').mkdir()
            (root / 'log').mkdir()
            (root / 'runner.php').write_text(RUNNER)
            cache = {}
            bodies = {'base': 'int transition_base(void) { return 1; }',
                      'consumer': 'extern int transition_base(void); int transition_consumer(void) { return transition_base(); }',
                      'other': '#include <string>\nextern "C" int transition_other(void) { return (int) std::string(128, 120).size(); }'}
            for short, body in bodies.items():
                directory = root / 'native-sources' / ('transition-' + short)
                directory.mkdir(parents=True)
                (directory / 'fixture.c').write_text(body)
                (directory / 'LICENSE').write_text('MIT fixture license')
                cache['transition-' + short] = {'source': {'cache_type': 'local', 'dirname': str(directory),
                    'hash': hashlib.sha256(body.encode()).hexdigest(), 'version': '1'}}

            (root / 'native-sources/transition-other/configure').write_text("PACKAGE_VERSION='1.2.3'\n")
            cache['transition-other']['source']['version'] = None

            def save_cache():
                (root / 'downloads/.cache.json').write_text(json.dumps(cache))

            def run(action, selected, success=True):
                if action == 'build' and 'other' in selected.split(','):
                    source = root / 'native-sources/transition-other'
                    source.mkdir(parents=True, exist_ok=True)
                    (source / 'fixture.c').write_text(bodies['other'])
                    (source / 'LICENSE').write_text('MIT fixture license')
                    (source / 'configure').write_text("PACKAGE_VERSION='1.2.3'\n")
                libc = os.environ.get('SPC_LIBC') or ('musl' if Path('/etc/alpine-release').is_file() else 'glibc')
                environment = dict(os.environ, SPC_LIBC=libc, SPC_TARGET='', SPC_TOOLCHAIN='StaticPHP\\Toolchain\\GccNativeToolchain',
                                   SPC_SKIP_TOOLCHAIN_CHECK='yes', SPC_ENABLE_LOG_FILE='0',
                                   CC=os.environ.get('CC') or shutil.which('cc'),
                                   CXX=os.environ.get('CXX') or shutil.which('g++'),
                                   BUILD_ROOT_PATH=str(root / 'buildroot'), SOURCE_PATH=str(root / 'source'),
                                   DOWNLOAD_PATH=str(root / 'downloads'), PKG_ROOT_PATH=str(root / 'pkgroot'))
                for name in ['BUILD_LIB_PATH', 'BUILD_BIN_PATH', 'BUILD_INCLUDE_PATH']:
                    environment.pop(name, None)
                result = subprocess.run(['php', str(root / 'runner.php'), str(SPC_ROOT), action, selected],
                                        cwd=root, env=environment, capture_output=True, text=True)
                if success:
                    self.assertEqual(result.returncode, 0, result.stderr + result.stdout)
                    return json.loads(result.stdout)
                self.assertNotEqual(result.returncode, 0, result.stderr + result.stdout)
                return result.stderr + result.stdout

            def signature(relative):
                path = root / 'buildroot' / relative
                return hashlib.sha256(path.read_bytes()).hexdigest(), path.stat().st_mtime_ns

            def compiled():
                return [json.loads(line) for line in (root / 'compiled.jsonl').read_text().splitlines()]

            save_cache()
            initial = run('build', 'base,consumer,other')
            self.assertEqual(initial['libraries']['transition-other']['version'], '1.2.3')
            self.assertFalse((root / 'native-sources/transition-other').exists())
            self.assertEqual((root / 'buildroot/license/transition-other/0.txt').read_text(), 'MIT fixture license')
            self.assertEqual(initial['libc'], os.environ.get('SPC_LIBC') or ('musl' if Path('/etc/alpine-release').is_file() else 'glibc'))
            self.assertNotIn('pending_rebuilds', initial)
            self.assertIn('gcc-runtime', initial['libraries'])
            self.assertTrue(initial['libraries']['gcc-runtime']['license_files'])
            self.assertIn('libstdc++.so.6', initial['libraries']['transition-other']['elf']['lib/libtransition-other.so.1']['needed'])
            self.assertEqual(compiled(), ['base', 'consumer', 'other'])
            consumer = signature('lib/libtransition-consumer.so.1')
            other = signature('lib/libtransition-other.so.1')
            source = root / 'native-sources/transition-base/fixture.c'
            source.write_text('int transition_base(void) { return 2; }')
            cache['transition-base']['source'].update(version='2', hash=hashlib.sha256(source.read_bytes()).hexdigest())
            save_cache()

            transition = run('build', 'base')
            self.assertEqual(compiled(), ['base', 'consumer', 'other', 'base'])
            self.assertFalse((root / 'buildroot/lib/libtransition-base.so.1').exists())
            self.assertEqual(transition['pending_rebuilds'], {'transition-consumer': {'libtransition-base.so.1': 'transition-base'}})
            self.assertEqual(signature('lib/libtransition-consumer.so.1'), consumer)
            self.assertEqual(signature('lib/libtransition-other.so.1'), other)
            rejection = run('reuse', 'consumer', success=False)
            self.assertRegex(rejection, r'(?:incomplete|pending|LIBRARY_REUSE|invalid)')
            self.assertEqual(compiled(), ['base', 'consumer', 'other', 'base'])

            final = run('build', 'consumer')
            self.assertNotIn('pending_rebuilds', final)
            self.assertEqual(compiled(), ['base', 'consumer', 'other', 'base', 'consumer'])
            self.assertEqual(signature('lib/libtransition-other.so.1'), other)
            self.assertEqual(final['libraries']['transition-consumer']['depends'], ['transition-base'])
            consumer_elf = final['libraries']['transition-consumer']['elf']['lib/libtransition-consumer.so.1']
            self.assertIn('libtransition-base.so.2', consumer_elf['needed'])
            self.assertNotIn('libtransition-base.so.1', consumer_elf['needed'])
            run('reuse', 'consumer')
            self.assertEqual(compiled(), ['base', 'consumer', 'other', 'base', 'consumer'])
            (root / 'check-other.c').write_text('extern int transition_other(void); int main(void) { return transition_other() == 128 ? 0 : 1; }')
            compiler = os.environ.get('CC') or shutil.which('cc')
            subprocess.run([compiler, str(root / 'check-other.c'), '-L' + str(root / 'buildroot/lib'),
                            '-ltransition-other', '-Wl,-rpath-link,' + str(root / 'buildroot/lib'),
                            '-o', str(root / 'check-other')], check=True, capture_output=True)
            runtime_environment = dict(os.environ, LD_LIBRARY_PATH=str(root / 'buildroot/lib'))
            subprocess.run([str(root / 'check-other')], env=runtime_environment, check=True)
            loader = subprocess.check_output(['ldd', str(root / 'check-other')], env=runtime_environment, text=True)
            self.assertIn(str(root / 'buildroot/lib/libstdc++.so.6'), loader)

            (root / 'check.c').write_text('extern int transition_consumer(void); int main(void) { return transition_consumer() == 2 ? 0 : 1; }')
            compiler = os.environ.get('CC') or shutil.which('cc')
            subprocess.run([compiler, str(root / 'check.c'), '-L' + str(root / 'buildroot/lib'),
                            '-ltransition-consumer', '-Wl,-rpath-link,' + str(root / 'buildroot/lib'),
                            '-o', str(root / 'check')], check=True, capture_output=True)
            subprocess.run([str(root / 'check')], env=dict(os.environ, LD_LIBRARY_PATH=str(root / 'buildroot/lib')), check=True)
            binary = root / 'buildroot/lib/libtransition-other.so.1'
            original = binary.read_bytes()
            binary.write_bytes(original + b'tampered')
            self.assertRegex(run('reuse', 'other', success=False), r'(?:changed|invalid|LIBRARY_REUSE)')
            binary.write_bytes(original)
            run('reuse', 'other')

            ownership_path = root / 'buildroot/.shared-library-ownership.json'
            original_ownership = ownership_path.read_bytes()
            bad_ownership = json.loads(original_ownership)
            bad_ownership['libraries']['transition-other']['files'].insert(0, '../unsafe')
            ownership_path.write_text(json.dumps(bad_ownership))
            protected_base = signature('lib/libtransition-base.so.2')
            protected_consumer = signature('lib/libtransition-consumer.so.1')
            self.assertIn('Unsafe shared SDK path', run('build', 'other', success=False))
            self.assertEqual(signature('lib/libtransition-base.so.2'), protected_base)
            self.assertEqual(signature('lib/libtransition-consumer.so.1'), protected_consumer)
            ownership_path.write_bytes(original_ownership)
            run('reuse', 'other')

            source.write_text('invalid C source;')
            cache['transition-base']['source'].update(version='3', hash=hashlib.sha256(source.read_bytes()).hexdigest())
            save_cache()
            self.assertIn('Native fixture compile failed', run('build', 'base', success=False))
            self.assertFalse((root / 'buildroot/lib/incomplete.a').is_symlink())
            failed = json.loads((root / 'buildroot/shared-libraries.json').read_text())
            self.assertNotIn('transition-base', failed['libraries'])
            self.assertIn('transition-consumer', failed['pending_rebuilds'])
            self.assertFalse((root / 'buildroot/lib/libtransition-base.so.3').exists())
            run('reuse', 'consumer', success=False)
            source.write_text('int transition_base(void) { return 2; }')
            cache['transition-base']['source'].update(version='2', hash=hashlib.sha256(source.read_bytes()).hexdigest())
            save_cache()
            recovered = run('build', 'base')
            self.assertNotIn('pending_rebuilds', recovered)
            run('reuse', 'consumer')



if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--spc-root', type=Path, default=Path(__file__).resolve().parents[1])
    args, remaining = parser.parse_known_args()
    SPC_ROOT = args.spc_root.resolve()
    unittest.main(argv=[__file__] + remaining)
