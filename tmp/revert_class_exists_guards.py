#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1] / "backend" / "app"
pat = re.compile(
    r"if\s*\(\s*!class_exists\(\s*__NAMESPACE__\s*\.\s*'\\\\([A-Za-z0-9_]+)'\s*,\s*false\s*\)\s*\)\s*\{"
)

stats = {"keep": 0, "reverted": 0, "files": 0}

for path in root.rglob("*.php"):
    text = path.read_text(encoding="utf-8")
    is_controller = "/Controllers/" in str(path).replace("\\", "/")

    def repl(m: re.Match) -> str:
        name = m.group(1)
        ahead = text[m.end() : m.end() + 240]
        defines = re.search(rf"\bclass\s+{name}\b", ahead) is not None
        if defines and is_controller:
            stats["keep"] += 1
            return m.group(0)
        stats["reverted"] += 1
        return f"if (!class_exists('{name}', false)) {{"

    text2 = pat.sub(repl, text)
    if text2 != text:
        path.write_text(text2, encoding="utf-8")
        stats["files"] += 1

print(stats)
