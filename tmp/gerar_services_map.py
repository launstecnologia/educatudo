#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

APP = Path(__file__).resolve().parents[1] / "backend/app"


def php_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


def main() -> None:
    entries: dict[str, tuple[str, str]] = {}
    for base in [APP / "Dominio", APP / "Modulos", APP / "Painel", APP / "Infra"]:
        if not base.exists():
            continue
        for f in base.rglob("*.php"):
            rel = f.relative_to(APP).as_posix()
            if "/Services/" not in rel:
                continue
            text = f.read_text(errors="replace")
            m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
            cm = re.search(r"\bclass\s+(\w+)", text)
            if not m or not cm:
                continue
            new_fqcn = m.group(1) + "\\" + cm.group(1)
            short = cm.group(1)
            if "Asaas" in m.group(1):
                old = "App\\Services\\Asaas\\" + short
            else:
                old = "App\\Services\\" + short
            entries[old] = (new_fqcn, rel)

    lines = ["<?php", "return ["]
    for old in sorted(entries):
        new, rel = entries[old]
        lines.append(f"    {php_quote(old)} => [{php_quote(new)}, {php_quote(rel)}],")
    lines.append("];")
    lines.append("")
    (APP / "Compat/services_map.php").write_text("\n".join(lines))
    print("services_map", len(entries))


if __name__ == "__main__":
    main()
