#!/usr/bin/env python3
"""Static guardrail for migration ownership and tenant FKs; not a DB substitute."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1] / 'database' / 'migrations'
files = sorted(root.glob('*.php'))
tables = {}
for f in files:
    source = f.read_text()
    # A single Laravel migration may create several tables. Check each one
    # separately, not just the first Schema::create() in a file.
    starts = list(re.finditer(r"Schema::create\('([^']+)'", source))
    for i, match in enumerate(starts):
        block_end = starts[i + 1].start() if i + 1 < len(starts) else len(source)
        tables[match.group(1)] = (f, source[match.start():block_end])
errors = []
for table, (file, source) in tables.items():
    fields = set(re.findall(r"\$table->(?:unsignedBigInteger|id|foreignId)\('([^']+)'\)", source))
    if '$table->id(' in source:
        fields.add('id')
    if {'organisation_id', 'brand_id'} <= fields and table != 'brands':
        pattern = r"\$table->foreign\(\s*\['organisation_id',\s*'brand_id'(?:\s*,\s*'[^']+')*\s*\]"
        if not re.search(pattern, source):
            errors.append(f'{table}: no composite foreign key to brands')
    for parent in re.findall(r"->on\('([^']+)'\)", source):
        if parent not in tables and parent not in ('roles', 'permissions'):
            errors.append(f'{table}: unknown referenced table {parent}')
        elif parent in tables and tables[parent][0].name > file.name:
            errors.append(f'{table}: references table created later: {parent}')
    if table in ('brand_voice_examples', 'outreach_enrollments'):
        if f"Schema::dropIfExists('{table}')" not in source:
            errors.append(f'{table}: wrong rollback table')
    for name in re.findall(r"\$table->(?:unique|index|foreign)\([^;]*?,\s*'([^']+)'\)", source, re.S):
        if len(name) > 64:
            errors.append(f'{table}: MySQL index/constraint name exceeds 64 characters: {name}')
if errors:
    print('\n'.join(errors), file=sys.stderr)
    sys.exit(1)
print(f'PASS: {len(tables)} application-owned tables; parent table order, scoped brands, rollback targets validated')
print('NOT CHECKED: vendor migrations, runtime FK data types/collations, executing MySQL DDL')
