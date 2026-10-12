#!/usr/bin/env python3
"""Fecha educainclui, conformidade e patrimonio: remove rotas do admin.php + PSR-4."""
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parents[1]
ADMIN = ROOT / "backend/config/routes/admin.php"


def strip_block_by_prefixes(text: str, prefixes: list[str], comment: str) -> str:
    lines = text.splitlines(keepends=True)
    out = []
    i = 0
    removed_any = False
    while i < len(lines):
        line = lines[i]
        is_route = line.strip().startswith("$router->") and any(p in line for p in prefixes)
        # comment header just before a block we care about
        if not is_route:
            # If next non-empty lines are only our routes, replace a section header
            out.append(line)
            i += 1
            continue
        if not removed_any:
            # replace previous comment line if it looks like a section header for this block
            if out and out[-1].strip().startswith("//"):
                out[-1] = comment + "\n"
            else:
                out.append(comment + "\n")
            removed_any = True
        # skip this and contiguous matching routes / blank / short comments inside block
        while i < len(lines):
            l = lines[i]
            if l.strip().startswith("$router->") and any(p in l for p in prefixes):
                i += 1
                continue
            if l.strip() == "":
                nxt = lines[i + 1] if i + 1 < len(lines) else ""
                if nxt.strip().startswith("$router->") and any(p in nxt for p in prefixes):
                    i += 1
                    continue
                break
            if l.strip().startswith("//") and not l.strip().startswith("// ──"):
                # skip inline section comments only if next is still our routes
                nxt_i = i + 1
                while nxt_i < len(lines) and lines[nxt_i].strip() == "":
                    nxt_i += 1
                nxt = lines[nxt_i] if nxt_i < len(lines) else ""
                if nxt.strip().startswith("$router->") and any(p in nxt for p in prefixes):
                    i += 1
                    continue
                break
            break
        removed_any = False  # allow next block to write its own comment
    return "".join(out)


def main() -> None:
    text = ADMIN.read_text()

    text = strip_block_by_prefixes(
        text,
        ["/admin/inclusao"],
        "// EducaInclui → Modulos/educainclui/routes.php",
    )
    text = strip_block_by_prefixes(
        text,
        ["/admin/conformidade"],
        "// Conformidade → Modulos/conformidade/routes.php",
    )
    text = strip_block_by_prefixes(
        text,
        ["/admin/patrimonio", "/admin/almoxarifado"],
        "// Patrimônio / almoxarifado → Modulos/patrimonio/routes.php",
    )

    ADMIN.write_text(text)
    for pat in ["inclusao", "conformidade", "patrimonio", "almoxarifado"]:
        # count remaining router lines
        n = len(re.findall(rf"\$router->.*{pat}", text))
        print(f"router lines with {pat}: {n}")

    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4["App\\Modulos\\EducaInclui\\"] = "app/Modulos/educainclui/"
    psr4["App\\Modulos\\Conformidade\\"] = "app/Modulos/conformidade/"
    psr4["App\\Modulos\\Patrimonio\\"] = "app/Modulos/patrimonio/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print("composer PSR-4 updated")


if __name__ == "__main__":
    main()
