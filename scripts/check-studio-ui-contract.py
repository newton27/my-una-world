#!/usr/bin/env python3
"""Check the native updater language contract and supported Recovery icon."""
from pathlib import Path
import re
import xml.etree.ElementTree as ET
root = Path(__file__).resolve().parents[1]
version = re.search(r"'version'\s*=>\s*'([^']+)'", (root/'install/config.php').read_text())[1]
errors = []
page = (root/'classes/MyUnaWorldStudioPage.php').read_text()
if "'icon' => 'life-ring'" in page:
    errors.append('Recovery uses life-ring, which UNA Lucide leaves unrendered')
for update in sorted((root/'updates').glob('*_' + version)):
    xml = ET.parse(update/'install/langs/en.xml')
    for key in ['_my_una_world_backup_intro', '_my_una_world_desc']:
        found = xml.find(".//string_upd[@name='" + key + "']")
        if found is None or not found.text:
            errors.append(str(update.relative_to(root)) + ': update-mode parser cannot upsert ' + key)
if errors:
    raise SystemExit('FAIL: ' + '\nFAIL: '.join(errors))
print('PASS: native language upserts and Recovery icon contract')
