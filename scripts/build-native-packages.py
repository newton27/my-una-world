#!/usr/bin/env python3
"""Build fixed-layout UNA 1.3.5 ZIPs from an exact Git tree; verify every byte."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import subprocess
import sys
import tarfile
import zipfile
import zlib

parser = argparse.ArgumentParser()
parser.add_argument('--source-ref', default='HEAD')
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
repo = Path(__file__).resolve().parents[1]
out = args.output.resolve()
out.mkdir(parents=True, exist_ok=True)

def git(*parts):
    return subprocess.check_output(['git', '-C', str(repo), *parts])

def digest(data):
    return hashlib.sha256(data).hexdigest()

ref = args.source_ref
source_tree = git('rev-parse', ref + '^{tree}').decode().strip()
try:
    source_commit = git('rev-parse', '--verify', ref + '^{commit}').decode().strip()
except subprocess.CalledProcessError:
    source_commit = None
source = out / 'source-validation'
if source.exists():
    shutil.rmtree(source)
source.mkdir()
archive = git('archive', '--format=tar', ref)
with tarfile.open(fileobj=io.BytesIO(archive)) as tar:
    tar.extractall(source, filter='data')
php = os.environ.get('PHP_BIN', '/workspace/scratch/a086edc801f5/composer-runtime/bin/php')
validation = subprocess.run([sys.executable, str(source / 'scripts/validate-candidate.py')], env={**os.environ, 'PHP_BIN': php}, cwd=source, capture_output=True, text=True)
validation_output = validation.stdout + validation.stderr
(out / 'validation.txt').write_text(validation_output)
if validation.returncode:
    raise RuntimeError(validation_output)
assertions = sum(map(int, re.findall(r'assertions? passed: (\d+)', validation_output)))
assert assertions == 222, (assertions, validation_output)
assert 'PASS: 13 test scripts; 68 PHP syntax checks.' in validation_output

tracked = sorted(p.relative_to(source).as_posix() for p in source.rglob('*') if p.is_file())
full_files = [p for p in tracked if p.startswith(('classes/', 'install/', 'template/')) or p in ['README.txt', 'action.php', 'download.php', 'report.php', 'status.php', 'std-icon.svg', 'studio-icon.svg']]
packages = [('My-UNA-World-1.3.5.zip', 'my_una_world/', {p: p for p in full_files}, None)]
for version in ['1.3.3', '1.3.4']:
    prefix = f'updates/{version}_1.3.5/'
    mapping = {p[len(prefix):]: p for p in tracked if p.startswith(prefix)}
    assert len(mapping) == 11, mapping
    packages.append((f'My-UNA-World-Update-{version}-to-1.3.5.zip', f'update_{version}_1.3.5/', mapping, version))

stamp = (1980, 1, 1, 0, 0, 0)

def zip_bytes(root, mapping):
    buffer = io.BytesIO()
    directories = {root}
    for path in mapping:
        parent = PurePosixPath(path).parent
        while str(parent) != '.':
            directories.add(root + str(parent) + '/')
            parent = parent.parent
    with zipfile.ZipFile(buffer, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        # UNA reads getNameIndex(0) as the package directory.
        for name in [root] + sorted(directories - {root}):
            info = zipfile.ZipInfo(name, stamp)
            info.create_system = 3
            info.external_attr = (0o40755 << 16) | 0x10
            info.compress_type = zipfile.ZIP_STORED
            z.writestr(info, b'')
        for name, original in sorted(mapping.items()):
            info = zipfile.ZipInfo(root + name, stamp)
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(info, (source / original).read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return buffer.getvalue()

results = []
for filename, root, mapping, version_from in packages:
    data = zip_bytes(root, mapping)
    assert data == zip_bytes(root, mapping), 'Deterministic rebuild mismatch: ' + filename
    path = out / filename
    path.write_bytes(data)
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        assert z.testzip() is None
        assert z.infolist()[0].filename == root and z.infolist()[0].is_dir()
        assert len(z.namelist()) == len(set(z.namelist()))
        assert all(name.startswith(root) and '..' not in PurePosixPath(name).parts for name in z.namelist())
        assert root + 'install/config.php' in z.namelist()
        assert sorted(n for n in z.namelist() if not n.endswith('/')) == sorted(root + p for p in mapping)
        for name, original in mapping.items():
            assert z.read(root + name) == (source / original).read_bytes()
        if version_from is None:
            assert "'version' => '1.3.5'" in z.read(root + 'install/config.php').decode()
            for icon in ['std-icon.svg', 'studio-icon.svg', 'template/images/icons/std-icon.svg', 'template/images/icons/studio-icon.svg']:
                assert z.read(root + icon) == (source / 'std-icon.svg').read_bytes()
        else:
            assert z.read(root + 'source/template/images/icons/std-icon.svg') == (source / 'template/images/icons/std-icon.svg').read_bytes()
            config = z.read(root + 'install/config.php').decode()
            assert f"'version_from' => '{version_from}'" in config
            assert "'version_to' => '1.3.5'" in config
            for name, original in mapping.items():
                if name.startswith('source/'):
                    assert z.read(root + name) == (source / name[len('source/'):]).read_bytes()
    results.append({'filename': filename, 'bytes': len(data), 'sha256': digest(data), 'first_zip_entry': root, 'version_from': version_from, 'version_to': '1.3.5', 'file_count': len(mapping), 'zip_integrity': 'PASS', 'native_first_entry_layout': 'PASS', 'deterministic_rebuild': 'PASS', 'files': [{'path': root + name, 'source_path': original, 'bytes': (source / original).stat().st_size, 'sha256': digest((source / original).read_bytes())} for name, original in sorted(mapping.items())]})

php_check = r'''foreach(array_slice($argv, 1) as $path) { $z=new ZipArchive(); if($z->open($path, ZipArchive::CHECKCONS)!==true)throw new RuntimeException('ZIP consistency: '.$path);$root=$z->getNameIndex(0);if(substr($root,-1)!=='/'||$z->locateName($root.'install/config.php')===false)throw new RuntimeException('Native package root: '.$path);echo basename($path).': CHECKCONS PASS; getNameIndex(0)='.$root.PHP_EOL;$z->close();}'''
check = subprocess.run([php, '-r', php_check, *[str(out / r['filename']) for r in results]], capture_output=True, text=True)
(out / 'native-zip-layout.txt').write_text(check.stdout + check.stderr)
assert check.returncode == 0, check.stdout + check.stderr

historical_hashes = {'RELEASES/1.3.4/My-UNA-World-1.3.4.zip': 'd4d5a88c7bd06adc75d43c6ea23448c83a92777c6dcbf0c6d73978c4f5e62123', 'RELEASES/1.3.4/My-UNA-World-Update-1.3.3-to-1.3.4.zip': 'c169da1c95eada4c9ab65a0f9498a07df5e1935659932c4cf4be8b43ccfac9d0'}
for path, sha in historical_hashes.items():
    assert digest((source / path).read_bytes()) == sha
manifest = {'module': 'my_una_world', 'title': 'My UNA World', 'vendor': 'MyUNAWorld', 'price': 'Free', 'version': '1.3.5', 'status': 'CANDIDATE; native test gates pending', 'source_commit': source_commit, 'source_tree': source_tree, 'source_ref': ref, 'build': {'script': 'build_packages.py', 'script_sha256': digest(Path(__file__).read_bytes()), 'python': sys.version.split()[0], 'zlib': zlib.ZLIB_VERSION, 'zip_timestamp': '1980-01-01T00:00:00', 'compression': 'deflate level 9', 'directories_mode': '0755', 'files_mode': '0644'}, 'validation': {'scripts': 13, 'assertions': assertions, 'php_syntax_checks': 68, 'php_runtime': subprocess.check_output([php, '-v'], text=True).splitlines()[0], 'output_file': 'validation.txt', 'output_sha256': digest(validation_output.encode()), 'native_zip_layout_file': 'native-zip-layout.txt', 'native_zip_layout_sha256': digest((check.stdout + check.stderr).encode()), 'historical_1_3_4_assets': 'UNCHANGED'}, 'native_gates': {'fresh_install_1_3_5': 'NOT TESTED', 'native_update_1_3_3_to_1_3_5': 'NOT TESTED', 'native_update_1_3_4_to_1_3_5': 'NOT TESTED', 'target_site_performance': 'NOT TESTED', 'marketplace_and_downloaded_info': 'NOT TESTED'}, 'artifacts': results}
(out / 'PACKAGE-MANIFEST.json').write_text(json.dumps(manifest, indent=2) + '\n')
(out / 'SHA256SUMS.txt').write_text(''.join(f"{r['sha256']}  {r['filename']}\n" for r in results))
print(validation_output, end='')
print(check.stdout, end='')
for r in results:
    print(r['filename'], r['bytes'], r['sha256'])
print('Source commit:', source_commit)
print('Source tree:', source_tree)
