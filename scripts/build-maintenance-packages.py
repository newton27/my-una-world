#!/usr/bin/env python3
"""Build immutable native World maintenance packages from a committed Git checkpoint."""
import argparse
import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import tarfile
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--source-ref', default='HEAD')
parser.add_argument('--output', type=Path, required=True)
args = parser.parse_args()
repo = Path(__file__).resolve().parents[1]
out = args.output.resolve()
out.mkdir(parents=True, exist_ok=True)
git = lambda *parts: subprocess.check_output(['git', '-C', str(repo), *parts])
commit = git('rev-parse', args.source_ref + '^{commit}').decode().strip()
tree = git('rev-parse', args.source_ref + '^{tree}').decode().strip()
source = out / 'source-validation'
source.mkdir(exist_ok=False)
with tarfile.open(fileobj=io.BytesIO(git('archive', '--format=tar', args.source_ref))) as archive:
    archive.extractall(source, filter='data')
php = os.environ.get('PHP_BIN', 'php')
validation = subprocess.run([sys.executable, str(source / 'scripts/validate-candidate.py')],
    cwd=source, env={**os.environ, 'PHP_BIN': php}, capture_output=True, text=True)
(out / 'validation.txt').write_text(validation.stdout + validation.stderr)
if validation.returncode:
    raise RuntimeError(validation.stdout + validation.stderr)
version = re.search(r"'version'\s*=>\s*'([^']+)'", (source / 'install/config.php').read_text())[1]
assert version == '1.3.7'
full = {p.relative_to(source).as_posix(): p for p in source.rglob('*') if p.is_file()
    and (p.relative_to(source).parts[0] in ['classes', 'install', 'template']
         or p.relative_to(source).as_posix() in ['README.txt','action.php','download.php','report.php','status.php','std-icon.svg','studio-icon.svg'])}
update = source / 'updates/1.3.6_1.3.7'
payload = {p.relative_to(update).as_posix(): p for p in update.rglob('*') if p.is_file()}
assets = [('My-UNA-World-1.3.7.zip','my_una_world/',full,None),
          ('My-UNA-World-Update-1.3.6-to-1.3.7.zip','update_1.3.6_1.3.7/',payload,'1.3.6')]
def build(root, mapping):
    data = io.BytesIO()
    directories = {root}
    for relative in mapping:
        parent = PurePosixPath(relative).parent
        while str(parent) != '.':
            directories.add(root + str(parent) + '/')
            parent = parent.parent
    with zipfile.ZipFile(data, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name in [root] + sorted(directories - {root}):
            entry = zipfile.ZipInfo(name, (1980,1,1,0,0,0)); entry.create_system=3
            entry.external_attr=(0o40755 << 16) | 0x10
            archive.writestr(entry,b'')
        for relative,path in sorted(mapping.items()):
            entry=zipfile.ZipInfo(root+relative,(1980,1,1,0,0,0));entry.create_system=3
            entry.external_attr=0o100644 << 16
            archive.writestr(entry,path.read_bytes(),compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    return data.getvalue()
records=[]
for name,root,mapping,prior in assets:
    data=build(root,mapping); assert data==build(root,mapping)
    path=out/name;path.write_bytes(data)
    with zipfile.ZipFile(path) as archive:
        assert archive.testzip() is None and archive.infolist()[0].filename==root
        assert root+'install/config.php' in archive.namelist()
        for relative,original in mapping.items():
            assert archive.read(root+relative)==original.read_bytes()
            if relative.startswith('source/'):
                assert original.read_bytes()==(source/relative[7:]).read_bytes()
    subprocess.run([php,'-r', '$z=new ZipArchive();if($z->open($argv[1],ZipArchive::CHECKCONS)!==true)exit(1);',str(path)],check=True)
    records.append({'filename':name,'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest(),
        'version_from':prior,'version_to':version,'first_zip_entry':root,
        'files':[{'path':relative,'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),
                  'source_blob':hashlib.sha1(b'blob '+str(p.stat().st_size).encode()+b'\0'+p.read_bytes()).hexdigest()}
                 for relative,p in sorted(mapping.items())]})
manifest={'module':'my_una_world','version':version,'source_commit':commit,'source_tree':tree,
    'validation':'PASS','native_update':'NOT TESTED','fresh_install':'NOT TESTED','artifacts':records}
(out/'PACKAGE-MANIFEST.json').write_text(json.dumps(manifest,indent=2)+'\n')
(out/'SHA256SUMS.txt').write_text(''.join(x['sha256']+'  '+x['filename']+'\n' for x in records))
print(validation.stdout,end=''); print(json.dumps([{k:v for k,v in x.items() if k!='files'} for x in records],indent=2))
