#!/usr/bin/env python3
"""SEO/AI 2026-10 etap 5: cennik i poradnik wyboru agencji, link Cennik w stopce. Idempotentny."""
import json, os
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
P = os.path.join(ROOT, 'src/c/shared-lib.mjs')
s = open(P, encoding='utf-8').read()
def bt(x):
    if isinstance(x, str):
        assert '`' not in x and '${' not in x, x
        return '`' + x + '`'
    if isinstance(x, (int, float)): return str(x)
    if isinstance(x, list): return '[' + ','.join(bt(i) for i in x) + ']'
    if isinstance(x, dict): return '{' + ','.join(f'{k}:{bt(v)}' for k, v in x.items()) + '}'
    raise TypeError(x)
for a in json.load(open(os.path.join(ROOT, 'tools/seo-articles2.json'), encoding='utf-8')):
    if f'{{s:`{a["s"]}`' not in s:
        s = s.replace('],P=[...N.map(', ',' + bt(a) + '],P=[...N.map(', 1)
old = "l(`a`,{href:t+`/agencja-ecommerce`,children:`Agencja e-commerce`})"
if '/wiedza/cennik`,children:`Cennik`' not in s:
    assert old in s
    s = s.replace(old, old + ",l(`a`,{href:t+`/wiedza/cennik`,children:`Cennik`})", 1)
open(P, 'w', encoding='utf-8').write(s)
print('ok')
