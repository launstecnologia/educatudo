#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1] / "backend" / "app"
changed = []
pat = re.compile(
    r"if\s*\(\s*!class_exists\(\s*'([A-Za-z0-9_]+)'\s*,\s*false\s*\)\s*\)\s*\{"
)

for path in root.rglob("*.php"):
    text = path.read_text(encoding="utf-8")

    def repl(m: re.Match) -> str:
        name = m.group(1)
        return f"if (!class_exists(__NAMESPACE__ . '\\\\{name}', false)) {{"

    text2 = pat.sub(repl, text)
    if text2 != text:
        path.write_text(text2, encoding="utf-8")
        changed.append(str(path.relative_to(root.parent)))

print(f"fixed {len(changed)} files")
for c in changed:
    print(" ", c)
