#!/usr/bin/env python3
from pathlib import Path
import re

APP = Path(__file__).resolve().parents[1] / "backend/app"

for f in APP.rglob("*.php"):
    if "/Services/" not in f.as_posix():
        continue
    text = f.read_text(errors="replace")
    ns_m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
    if not ns_m:
        continue
    ns = ns_m.group(1)
    uses = set(re.findall(r"use\s+(App\\[\w\\]+)\\(\w+)\s*;", text))
    use_shorts = {u[1] for u in uses}
    # also use App\Services\X
    use_shorts |= set(re.findall(r"use\s+App\\Services\\(?:Asaas\\)?(\w+)\s*;", text))
    for m in re.finditer(r"(?<![\\\w])new\s+([A-Z]\w*Service)\s*\(", text):
        name = m.group(1)
        if name in use_shorts or re.search(rf"\bclass\s+{re.escape(name)}\b", text):
            continue
        print(f"{f.relative_to(APP)}: new {name}() ns={ns}")
    for m in re.finditer(r"(?<![\\\w])\?([A-Z]\w*Service)\s+\$", text):
        name = m.group(1)
        if name in use_shorts or re.search(rf"\bclass\s+{re.escape(name)}\b", text):
            continue
        print(f"{f.relative_to(APP)}: ?{name} $ ns={ns}")
