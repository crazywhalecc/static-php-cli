#!/usr/bin/env python3
"""Compile the real zlib recipe, compare static output to a baseline, and run consumers."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


def run(arguments, cwd, environment=None):
    result = subprocess.run(arguments, cwd=cwd, env=environment, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(' '.join(map(str, arguments)) + '\n' + result.stdout + result.stderr)
    return result.stdout


def build(spc, archive, version, root, shared):
    (root / 'downloads').mkdir(parents=True)
    (root / 'log').mkdir()
    target = root / 'downloads' / archive.name
    shutil.copyfile(archive, target)
    (root / 'downloads/.cache.json').write_text(json.dumps({'zlib': {'source': {
        'cache_type': 'archive', 'filename': target.name,
        'hash': hashlib.sha1(target.read_bytes()).hexdigest(), 'version': version}}}))
    runtime = root / 'runtime/lib'
    flags = '-O2 -g -fPIC -ffile-prefix-map=' + str(root) + '=/spc-native-fixture'
    configuration = {
        'extensions': [], 'shared-extensions': [], 'libs': ['zlib'], 'sapi': [],
        'build-options': {'no-strip': True}, 'craft-options': {'doctor': False, 'download': False},
        'extra-env': {'SPC_TOOLCHAIN': 'StaticPHP\\Toolchain\\GccNativeToolchain',
                      'SPC_DEFAULT_CFLAGS': flags, 'SPC_DEFAULT_CXXFLAGS': flags,
                      'SPC_DEFAULT_LDFLAGS': '-Wl,--as-needed'}}
    if shared:
        configuration['build-options'].update({'with-shared-libs': True, 'with-shared-libdir': str(runtime)})
    # JSON is a YAML subset and avoids a dependency on PyYAML.
    (root / 'craft.yml').write_text(json.dumps(configuration))
    env = dict(os.environ, SPC_SKIP_TOOLCHAIN_CHECK='yes', SPC_CONCURRENCY='1', SPC_ENABLE_LOG_FILE='0', SOURCE_DATE_EPOCH='1700000000')
    run(['php', str(spc / 'bin/spc'), 'craft', '--libs-only'], root, env)
    library = root / 'buildroot/lib'
    (root / 'check.c').write_text('#include <zlib.h>\n#include <string.h>\nint main(void) {\n'
                                'unsigned char output[100]; unsigned long size=sizeof(output);\n'
                                'return compress(output, &size, (const unsigned char *)"hello", 5) != Z_OK; }\n')
    if shared:
        runtime.parent.mkdir()
        shutil.copytree(library, runtime, symlinks=True)
        manifest = json.loads((root / 'buildroot/shared-libraries.json').read_text())
        assert set(manifest['libraries']) == {'zlib'}, manifest
        assert manifest['libraries']['zlib']['version'] == version
        assert manifest['libraries']['zlib']['license_files']
        run(['cc', str(root / 'check.c'), '-I' + str(root / 'buildroot/include'),
             '-L' + str(library), '-lz', '-Wl,-rpath,' + str(runtime), '-o', str(root / 'check')], root)
        dynamic = run(['readelf', '-d', str(root / 'check')], root)
        assert 'libz.so.1' in dynamic, dynamic
        assert str(runtime) in dynamic, dynamic
        assert 'libz.so.1' in run(['readelf', '-d', str(library / 'libz.so')], root)
    else:
        assert not (root / 'buildroot/shared-libraries.json').exists()
        assert not (library / 'libz.so').exists()
        run(['cc', str(root / 'check.c'), '-I' + str(root / 'buildroot/include'),
             str(library / 'libz.a'), '-o', str(root / 'check')], root)
        dynamic = run(['readelf', '-d', str(root / 'check')], root)
        assert 'libz.so' not in dynamic, dynamic
    clean = dict(os.environ)
    clean.pop('LD_LIBRARY_PATH', None)
    run([str(root / 'check')], root, clean)
    return hashlib.sha256((library / 'libz.a').read_bytes()).hexdigest() if not shared else None


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--archive', required=True, type=Path)
    parser.add_argument('--version', default='1.3.1')
    parser.add_argument('--spc-root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--baseline-root', type=Path)
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='spc-native-zlib-') as temporary:
        root = Path(temporary)
        static_hash = build(args.spc_root.resolve(), args.archive.resolve(), args.version, root / 'static', False)
        build(args.spc_root.resolve(), args.archive.resolve(), args.version, root / 'shared', True)
        if args.baseline_root:
            baseline_hash = build(args.baseline_root.resolve(), args.archive.resolve(), args.version, root / 'baseline', False)
            assert static_hash == baseline_hash, (static_hash, baseline_hash)
            print('PASS: static libz.a is byte-identical to the baseline')
        print('PASS: native static and shared consumers run without LD_LIBRARY_PATH')


if __name__ == '__main__':
    main()
