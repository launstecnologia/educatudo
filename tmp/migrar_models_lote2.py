#!/usr/bin/env python3
"""Lote 2: Models Education restantes → Modulos donos."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

MOVES = [
    ("Education/Ocorrencia.php", "Modulos/ocorrencias/Models", "App\\Modulos\\Ocorrencias\\Models"),
    ("Education/ConselhoSessao.php", "Modulos/conselho-classe/Models", "App\\Modulos\\ConselhoClasse\\Models"),
    ("Education/RegraAcademica.php", "Modulos/regras-academicas/Models", "App\\Modulos\\RegrasAcademicas\\Models"),
    ("Education/ClassDiary.php", "Modulos/diario/Models", "App\\Modulos\\Diario\\Models"),
    ("Education/SchoolAbsence.php", "Modulos/vida-escolar/Models", "App\\Modulos\\VidaEscolar\\Models"),
    ("Education/ResultadoAcademico.php", "Modulos/fechamento/Models", "App\\Modulos\\Fechamento\\Models"),
    ("Education/HistoricoDocumento.php", "Modulos/modelos-documentos/Models", "App\\Modulos\\ModelosDocumentos\\Models"),
]


def rewrite_ns(content: str, new_ns: str) -> str:
    if re.search(r"namespace\s+[\w\\]+;", content):
        return re.sub(
            r"namespace\s+[\w\\]+;",
            lambda _m: "namespace " + new_ns + ";",
            content,
            count=1,
        )
    return re.sub(
        r"^<\?php\s*",
        lambda _m: "<?php\n\nnamespace " + new_ns + ";\n\n",
        content,
        count=1,
    )


def write_shim(path: Path, require_from_app: str, new_fqcn: str, old_fqcn: str, depth: int) -> None:
    short = new_fqcn.rsplit("\\", 1)[-1]
    path.write_text(
        "<?php\n"
        f"/** Shim → {require_from_app} (sem duplicar código). */\n"
        f"require_once dirname(__DIR__, {depth}) . '/{require_from_app}';\n"
        f"if (!class_exists(\\{old_fqcn}::class, false)) {{\n"
        f"    class_alias(\\{new_fqcn}::class, \\{old_fqcn}::class);\n"
        f"}}\n"
        f"if (!class_exists('{short}', false)) {{\n"
        f"    class_alias(\\{new_fqcn}::class, '{short}');\n"
        f"}}\n"
    )


def main() -> None:
    # ensure composer has Fechamento + ModelosDocumentos PSR-4
    for rel, dst_dir, new_ns in MOVES:
        src = APP / "Models" / rel
        if not src.exists():
            print("skip", rel)
            continue
        text = src.read_text()
        if "Shim →" in text[:200]:
            print("already", rel)
            continue
        short = Path(rel).name
        dst = APP / dst_dir / short
        new_fqcn = f"{new_ns}\\{short.replace('.php','')}"
        m = re.search(r"namespace\s+([\w\\]+);", text)
        old_ns = m.group(1) if m else "App\\Models\\Education"
        old_fqcn = f"{old_ns}\\{short.replace('.php','')}"
        depth = 1 + rel.count("/")
        if dst.exists() and dst.stat().st_size > 500 and "Shim →" not in dst.read_text()[:200]:
            print("target exists shim-only", rel)
            write_shim(src, f"{dst_dir}/{short}", new_fqcn, old_fqcn, depth)
            continue
        dst.parent.mkdir(parents=True, exist_ok=True)
        dst.write_text(rewrite_ns(text, new_ns))
        write_shim(src, f"{dst_dir}/{short}", new_fqcn, old_fqcn, depth)
        print("OK", rel, "→", dst_dir)
    print("DONE")


if __name__ == "__main__":
    main()
