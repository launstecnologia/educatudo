#!/usr/bin/env python3
"""Move leftovers de redacoes/jornadas/provas e corrige use AdminBaseController."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

MAP = {
    "redacoes": {
        "handlers": {
            "Essays/TeacherEssayController@": "Modulos/redacoes/TeacherEssayController@",
            "Essays/TeacherRedacaoLivreController@": "Modulos/redacoes/TeacherRedacaoLivreController@",
            "Essays/StudentEssayController@": "Modulos/redacoes/StudentEssayController@",
            "Essays/AdminEssayController@": "Modulos/redacoes/AdminEssayController@",
            "Essays/AdminEssayAnalyticsController@": "Modulos/redacoes/AdminEssayAnalyticsController@",
        },
        "hint": ("redacao", "essay"),
    },
    "jornadas": {
        "handlers": {
            "Education/JourneyController@": "Modulos/jornadas/JourneyController@",
            "Education/AdminJourneyController@": "Modulos/jornadas/AdminJourneyController@",
            "Teacher/TeacherJourneyController@": "Modulos/jornadas/TeacherJourneyController@",
        },
        "hint": ("jornada", "journey"),
    },
    "provas": {
        "handlers": {
            "Exams/ExamController@": "Modulos/provas/ExamController@",
            "Exams/ExamBlockController@": "Modulos/provas/ExamBlockController@",
            "Exams/MockExamController@": "Modulos/provas/MockExamController@",
            "Exams/ServeExamQuestionImageController@": "Modulos/provas/ServeExamQuestionImageController@",
            "Admin/ProvasAlunoAssistenteController@": "Modulos/provas/ProvasAlunoAssistenteController@",
            "Admin/ProvasAlunoMcpController@": "Modulos/provas/ProvasAlunoMcpController@",
            "Admin/ProvasProfessorMcpController@": "Modulos/provas/ProvasProfessorMcpController@",
        },
        "hint": ("prova", "exam", "bloco"),
    },
}


def rewrite(line: str, handlers: dict) -> str | None:
    l2 = line
    hit = False
    for old, new in handlers.items():
        if old in l2:
            l2 = l2.replace(old, new)
            hit = True
    return l2 if hit else None


def process_file(route_file: str) -> None:
    p = ROOT / route_file
    lines = p.read_text().splitlines(keepends=True)
    out = []
    moved = {k: [] for k in MAP}
    for line in lines:
        if not line.strip().startswith("$router->"):
            out.append(line)
            continue
        handled = False
        for chave, cfg in MAP.items():
            low = line.lower()
            if not any(h in low for h in cfg["hint"]):
                continue
            # avoid unrelated: avaliacoes hub, etc.
            if chave == "provas" and "/admin/avaliacoes'" in line:
                continue
            nw = rewrite(line, cfg["handlers"])
            if nw and "Modulos/" in nw:
                moved[chave].append(nw.rstrip("\n"))
                handled = True
                break
        if not handled:
            out.append(line)

    # write leftovers comments once per module if we removed something
    text = "".join(out)
    # collapse duplicate blank comments later
    p.write_text(text)

    for chave, routes in moved.items():
        if not routes:
            continue
        rp = ROOT / f"backend/app/Modulos/{chave}/routes.php"
        existing = rp.read_text()
        add = []
        for r in routes:
            if r not in existing:
                add.append(r)
        if add:
            rp.write_text(existing.rstrip() + "\n" + "\n".join(add) + "\n")
        print(f"{route_file}: moved {len(routes)} -> {chave} (new {len(add)})")


def fix_uses() -> None:
    for mod, ns in [
        ("redacoes", "Redacoes"),
        ("jornadas", "Jornadas"),
        ("provas", "Provas"),
        ("ava", "Ava"),
        ("apostilas", "Apostilas"),
        ("exercicios", "Exercicios"),
        ("minicursos", "Minicursos"),
        ("aulas-online", "AulasOnline"),
        ("mural", "Mural"),
        ("boletins", "Boletins"),
    ]:
        d = ROOT / "backend/app/Modulos" / mod / "Controllers"
        if not d.exists():
            continue
        for p in d.glob("*.php"):
            t = p.read_text()
            if "extends AdminBaseController" not in t:
                continue
            if "use App\\Controllers\\Admin\\AdminBaseController" in t:
                continue
            t2 = re.sub(
                r"(namespace App\\Modulos\\\w+\\Controllers;)",
                r"\1\n\nuse App\\Controllers\\Admin\\AdminBaseController;",
                t,
                count=1,
            )
            if t2 != t:
                p.write_text(t2)
                print("AdminBase use", p.relative_to(ROOT))


if __name__ == "__main__":
    for f in [
        "backend/config/routes/admin.php",
        "backend/config/routes/teacher.php",
        "backend/config/routes/student.php",
    ]:
        process_file(f)
    fix_uses()
