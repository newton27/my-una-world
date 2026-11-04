#!/usr/bin/env python3
"""Run the entire standalone suite and lint every shipped PHP file."""
import os
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
subprocess.run([sys.executable, str(root / 'scripts/check-studio-ui-contract.py')], check=True)
php = os.environ.get('PHP_BIN', 'php')
tests = sorted((root / 'tests').glob('test_*.php'))
for test in tests:
    result = subprocess.run([php, '-d', 'error_reporting=E_ALL', '-d', 'display_errors=1', str(test)], cwd=root)
    if result.returncode:
        sys.exit(result.returncode)
files = sorted(root.rglob('*.php'))
for file in files:
    result = subprocess.run([php, '-l', str(file)], capture_output=True, text=True)
    if result.returncode:
        print(result.stdout + result.stderr)
        sys.exit(result.returncode)
print(f'PASS: {len(tests)} test scripts; {len(files)} PHP syntax checks.')
