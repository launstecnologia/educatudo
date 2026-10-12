#!/usr/bin/env python3
"""
Opção A: move Models/ → Modulos/{slug}/Models (código único + shim no caminho antigo).
"""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

# (src relativo a app/Models, modulo_slug, PascalCase do módulo)
MOVES: list[tuple[str, str, str]] = [
    # provas
    ("Exams/Exam.php", "provas", "Provas"),
    ("Exams/ExamBlock.php", "provas", "Provas"),
    ("Exams/ExamBlockModel.php", "provas", "Provas"),
    ("Exams/ExamBlockManualGrade.php", "provas", "Provas"),
    ("Exams/ExamEvaluationType.php", "provas", "Provas"),
    ("Exams/TeacherExam.php", "provas", "Provas"),
    ("Exams/NotaTipoFinal.php", "provas", "Provas"),
    ("Exams/ProvaLogEvento.php", "provas", "Provas"),
    ("Simulados/SimuladosCategoria.php", "provas", "Provas"),
    ("Simulados/SimuladosProva.php", "provas", "Provas"),
    ("Simulados/SimuladosBanca.php", "provas", "Provas"),
    ("Simulados/SimuladosQuestao.php", "provas", "Provas"),
    ("Simulados/SimuladosQuestaoArquivo.php", "provas", "Provas"),
    ("Simulados/SimuladosAlternativa.php", "provas", "Provas"),
    # ava
    ("Ava/LessonComment.php", "ava", "Ava"),
    ("Ava/ActivitySubmission.php", "ava", "Ava"),
    ("Ava/DisciplineEnrollment.php", "ava", "Ava"),
    ("Ava/DisciplineEvaluation.php", "ava", "Ava"),
    ("Ava/LessonProgress.php", "ava", "Ava"),
    ("Ava/Certificate.php", "ava", "Ava"),
    ("Ava/Lesson.php", "ava", "Ava"),
    ("Ava/Discipline.php", "ava", "Ava"),
    ("Ava/Module.php", "ava", "Ava"),
    ("Ava/Course.php", "ava", "Ava"),
    ("Ava/LiveClass.php", "ava", "Ava"),
    ("Ava/Activity.php", "ava", "Ava"),
    ("Ava/Rubric.php", "ava", "Ava"),
    # redacoes
    ("Essays/EssaySubmission.php", "redacoes", "Redacoes"),
    ("Essays/EssayCorrection.php", "redacoes", "Redacoes"),
    ("Essays/EssayCriterion.php", "redacoes", "Redacoes"),
    ("Essays/EssayPrompt.php", "redacoes", "Redacoes"),
    ("Essays/EssayBoard.php", "redacoes", "Redacoes"),
    ("Essays/EssayProposal.php", "redacoes", "Redacoes"),
    ("Essays/EssayTextType.php", "redacoes", "Redacoes"),
    ("RedacaoLivreEnvio.php", "redacoes", "Redacoes"),
    ("RedacaoLivreCorrecao.php", "redacoes", "Redacoes"),
    # forum
    ("Forum/ForumTopic.php", "forum", "Forum"),
    ("Forum/ForumAttachment.php", "forum", "Forum"),
    # flashcards
    ("Study/FlashcardTemplate.php", "flashcards", "Flashcards"),
    ("Study/FlashcardExplicacao.php", "flashcards", "Flashcards"),
    # notificacoes
    ("Notifications/Notification.php", "notificacoes", "Notificacoes"),
    ("Notifications/NotificationRecipient.php", "notificacoes", "Notificacoes"),
    # boletins
    ("System/BoletimConfig.php", "boletins", "Boletins"),
    ("Education/JourneyBoletimLancamento.php", "boletins", "Boletins"),
    # aulas-online
    ("Education/OnlineClass.php", "aulas-online", "AulasOnline"),
    # jornadas (lesson plan / resultado próximos a jornadas)
    ("Education/LessonPlan.php", "jornadas", "Jornadas"),
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
    path.parent.mkdir(parents=True, exist_ok=True)
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
    moved = 0
    for rel, slug, pascal in MOVES:
        src = APP / "Models" / rel
        if not src.exists():
            print("skip missing", rel)
            continue
        text = src.read_text()
        if "Shim →" in text or text.count("\n") < 15 and "class_alias" in text:
            print("already shim", rel)
            continue

        short = Path(rel).stem
        new_ns = f"App\\Modulos\\{pascal}\\Models"
        new_fqcn = f"{new_ns}\\{short}"
        m = re.search(r"namespace\s+([\w\\]+);", text)
        old_ns = m.group(1) if m else "App\\Models"
        old_fqcn = f"{old_ns}\\{short}" if not old_ns.endswith(short) else old_ns
        # if namespace was App\Models\Exams, old fqcn = App\Models\Exams\Exam
        if m and not old_ns.endswith("\\" + short):
            old_fqcn = f"{old_ns}\\{short}"
        else:
            old_fqcn = f"App\\Models\\{short}"

        dst_rel = f"Modulos/{slug}/Models/{short}.php"
        dst = APP / dst_rel
        dst.parent.mkdir(parents=True, exist_ok=True)

        content = rewrite_ns(text, new_ns)
        dst.write_text(content)

        # depth: Models/Exams/X.php → dirname 2 = app; Models/X.php → dirname 1 = app
        depth = 1 + rel.count("/")
        write_shim(src, dst_rel, new_fqcn, old_fqcn, depth)
        print(f"OK {rel} → {dst_rel}")
        moved += 1

    print(f"DONE moved={moved}")


if __name__ == "__main__":
    main()
