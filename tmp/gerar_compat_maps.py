#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re

APP = Path(__file__).resolve().parents[1] / "backend/app"


def php_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


def write_php_map(path: Path, entries: dict[str, tuple[str, str]]) -> None:
    lines = ["<?php", "return ["]
    for old in sorted(entries):
        new, rel = entries[old]
        lines.append(f"    {php_quote(old)} => [{php_quote(new)}, {php_quote(rel)}],")
    lines.append("];")
    lines.append("")
    path.write_text("\n".join(lines))
    print(path.name, len(entries))


def write_controllers() -> None:
    entries: dict[str, tuple[str, str]] = {}
    for base in [APP / "Dominio", APP / "Modulos", APP / "Painel", APP / "Api", APP / "Pwa", APP / "Publico"]:
        if not base.exists():
            continue
        for f in base.rglob("*.php"):
            rel = f.relative_to(APP).as_posix()
            if "/Controllers/" not in rel:
                continue
            text = f.read_text(errors="replace")
            m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
            cm = re.search(r"\bclass\s+(\w+)", text)
            if not m or not cm:
                continue
            new_fqcn = m.group(1) + "\\" + cm.group(1)
            short = cm.group(1)
            olds = ["App\\Controllers\\" + short]
            if rel.startswith("Api/"):
                olds.append("App\\Controllers\\Api\\" + short)
                if "/V1/" in rel:
                    olds.append("App\\Controllers\\Api\\V1\\" + short)
            if rel.startswith("Pwa/"):
                olds.append("App\\Controllers\\Pwa\\" + short)
            if rel.startswith("Publico/"):
                olds.append("App\\Controllers\\Public\\" + short)
            if rel.startswith("Painel/Admin/") or rel.startswith("Dominio/"):
                olds.append("App\\Controllers\\Admin\\" + short)
            if rel.startswith("Dominio/Escola/"):
                olds.append("App\\Controllers\\Education\\" + short)
            if rel.startswith("Painel/Master/"):
                olds.append("App\\Controllers\\Master\\" + short)
            if rel.startswith("Painel/Auth/"):
                olds.append("App\\Controllers\\Auth\\" + short)
            if rel.startswith("Painel/User/"):
                olds.append("App\\Controllers\\User\\" + short)
            if rel.startswith("Painel/Professor/"):
                olds.append("App\\Controllers\\Teacher\\" + short)
            if rel.startswith("Painel/Aluno/"):
                olds.append("App\\Controllers\\Student\\" + short)
            if "/Api/Controllers/V1/" in rel:
                olds.append("App\\Controllers\\Api\\V1\\" + short)
            elif rel.startswith("Painel/Api/"):
                olds.append("App\\Controllers\\Api\\" + short)
            for needle, folder in [
                ("Modulos/provas/", "Exams"),
                ("Modulos/redacoes/", "Essays"),
                ("Modulos/ava/", "Ava"),
                ("Modulos/forum/", "Forum"),
                ("Modulos/exercicios/", "Exercises"),
                ("Modulos/apostilas/", "Apostilas"),
                ("Modulos/notificacoes/", "Notifications"),
                ("Modulos/flashcards/", "Study"),
                ("Modulos/educainclui/", "EducaInclui"),
                ("Modulos/modelos-documentos/", "Declarations"),
                ("Modulos/educahits/", "EducaHits"),
                ("Modulos/educalabs/", "EducaLabs"),
                ("Modulos/external-apps/", "ExternalApps"),
                ("Modulos/jogos/", "Games"),
                ("Modulos/notes/", "Notes"),
            ]:
                if needle in rel:
                    olds.append("App\\Controllers\\" + folder + "\\" + short)
            for needle, folder in [
                ("Painel/Games/", "Games"),
                ("Painel/Integrations/", "Integrations"),
                ("Painel/Webhooks/", "Webhooks"),
                ("Painel/Media/", "Media"),
                ("Painel/Public/", "Public"),
            ]:
                if rel.startswith(needle):
                    olds.append("App\\Controllers\\" + folder + "\\" + short)
            for old in olds:
                entries[old] = (new_fqcn, rel)
    write_php_map(APP / "Compat/controllers_map.php", entries)


def write_models() -> None:
    entries: dict[str, tuple[str, str]] = {}
    for base in [APP / "Dominio", APP / "Modulos", APP / "Painel"]:
        if not base.exists():
            continue
        for f in base.rglob("*.php"):
            rel = f.relative_to(APP).as_posix()
            if "/Models/" not in rel:
                continue
            text = f.read_text(errors="replace")
            m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
            cm = re.search(r"\bclass\s+(\w+)", text)
            if not m or not cm:
                continue
            new_fqcn = m.group(1) + "\\" + cm.group(1)
            short = cm.group(1)
            olds = ["App\\Models\\" + short]
            rules = [
                ("Dominio/Pessoas/", ["User"]),
                ("Dominio/Escola/", ["Education", "Bncc"]),
                ("Modulos/provas/", ["Exams", "Simulados"]),
                ("Modulos/ava/", ["Ava"]),
                ("Modulos/redacoes/", ["Essays"]),
                ("Modulos/forum/", ["Forum"]),
                ("Modulos/flashcards/", ["Study"]),
                ("Modulos/notificacoes/", ["Notifications", "PushNotifications"]),
                ("Modulos/boletins/", ["System", "Education"]),
                ("Modulos/financeiro/", ["Finance"]),
                ("Modulos/matricula/", ["Enrollment"]),
                ("Modulos/educainclui/", ["EducaInclui"]),
                ("Modulos/modelos-documentos/", ["Education"]),
                ("Painel/Admin/Models/", ["System"]),
            ]
            for needle, folders in rules:
                if needle in rel:
                    for folder in folders:
                        olds.append("App\\Models\\" + folder + "\\" + short)
            if any(
                x in rel
                for x in [
                    "Modulos/jornadas/",
                    "Modulos/aulas-online/",
                    "Modulos/vida-escolar/",
                    "Modulos/fechamento/",
                    "Modulos/diario/",
                    "Modulos/ocorrencias/",
                    "Modulos/regras-academicas/",
                    "Modulos/conselho-classe/",
                ]
            ):
                olds.append("App\\Models\\Education\\" + short)
            for old in olds:
                entries[old] = (new_fqcn, rel)
    write_php_map(APP / "Compat/models_map.php", entries)


if __name__ == "__main__":
    write_controllers()
    write_models()
