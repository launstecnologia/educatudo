#!/usr/bin/env python3
"""Move Controllers reais restantes → Modulos/Dominio + shim (sem duplicar)."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

# (src rel app, dst dir rel app, new ns, shim depth from src parent chain to app)
MOVES = [
    ("Controllers/Exercises/ExerciseController.php", "Modulos/exercicios/Controllers", "App\\Modulos\\Exercicios\\Controllers", 2),
    ("Controllers/Exercises/CustomExerciseController.php", "Modulos/exercicios/Controllers", "App\\Modulos\\Exercicios\\Controllers", 2),
    ("Controllers/Education/LessonPlanController.php", "Modulos/jornadas/Controllers", "App\\Modulos\\Jornadas\\Controllers", 2),
    ("Controllers/Notifications/UserNotificationController.php", "Modulos/notificacoes/Controllers", "App\\Modulos\\Notificacoes\\Controllers", 2),
    ("Services/StudentStatusService.php", "Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services", 1),
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
    for src_rel, dst_dir, new_ns, depth in MOVES:
        src = APP / src_rel
        if not src.exists():
            print("skip missing", src_rel)
            continue
        text = src.read_text()
        if text.lstrip().startswith("<?php") and "Shim →" in text[:300]:
            print("already", src_rel)
            continue
        short = Path(src_rel).name
        dst = APP / dst_dir / short
        new_fqcn = f"{new_ns}\\{short.replace('.php','')}"
        m = re.search(r"namespace\s+([\w\\]+);", text)
        old_ns = m.group(1) if m else "App\\Controllers"
        old_fqcn = f"{old_ns}\\{short.replace('.php','')}"

        if dst.exists() and "Shim →" not in dst.read_text()[:200] and dst.stat().st_size > 1000:
            print("target exists shim-only", src_rel)
            write_shim(src, f"{dst_dir}/{short}", new_fqcn, old_fqcn, depth)
            continue

        dst.parent.mkdir(parents=True, exist_ok=True)
        dst.write_text(rewrite_ns(text, new_ns))
        write_shim(src, f"{dst_dir}/{short}", new_fqcn, old_fqcn, depth)
        print("OK", src_rel, "→", dst_dir)

    # AlunoMovimentacao: StudentStatusService now same/neighbouring ns — add use if needed
    mov = APP / "Dominio/Pessoas/Services/AlunoMovimentacaoService.php"
    if mov.exists():
        t = mov.read_text()
        if "new StudentStatusService" in t and "use App\\Dominio\\Pessoas\\Services\\StudentStatusService" not in t:
            # same namespace after move — OK unqualified
            print("AlunoMovimentacao: StudentStatusService same ns OK")
    print("DONE")


if __name__ == "__main__":
    main()
