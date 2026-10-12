#!/usr/bin/env python3
"""
Opção A — eliminar app/Controllers e app/Models.

1) Move Controllers reais → Dominio / Modulos / Painel
2) Move Models reais restantes → donos
3) Gera Compat/models_map.php (FQCN antigo → arquivo novo + alias)
4) NÃO deixa shims em Controllers/Models (pastas serão removidas)
"""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"
CTRL = APP / "Controllers"
MODELS = APP / "Models"

# Handler prefix / filename → (dst_dir_rel, new_ns)
# Mais específico primeiro.
ADMIN_MAP: dict[str, tuple[str, str]] = {
    # Domínio Escola
    "SchoolSettingsAdminController.php": ("Dominio/Escola/Controllers", "App\\Dominio\\Escola\\Controllers"),
    "SchoolCalendarController.php": ("Dominio/Escola/Controllers", "App\\Dominio\\Escola\\Controllers"),
    "CoursePlanController.php": ("Dominio/Escola/Controllers", "App\\Dominio\\Escola\\Controllers"),
    "SaudeAcademicaController.php": ("Dominio/Escola/Controllers", "App\\Dominio\\Escola\\Controllers"),
    # Módulos
    "SchoolAbsenceController.php": ("Modulos/vida-escolar/Controllers", "App\\Modulos\\VidaEscolar\\Controllers"),
    "ResultadoFinalAdminController.php": ("Modulos/fechamento/Controllers", "App\\Modulos\\Fechamento\\Controllers"),
    "FacialDeviceAdminController.php": ("Modulos/presenca/Controllers", "App\\Modulos\\Presenca\\Controllers"),
    "FacialRecognitionController.php": ("Modulos/presenca/Controllers", "App\\Modulos\\Presenca\\Controllers"),
    "AvatarAlunoAdminController.php": ("Dominio/Pessoas/Controllers", "App\\Dominio\\Pessoas\\Controllers"),
    "JornadasAlunoMcpController.php": ("Modulos/jornadas/Controllers", "App\\Modulos\\Jornadas\\Controllers"),
    "AssistenteConsultaMcpController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "TeacherDocsController.php": ("Dominio/Pessoas/Controllers", "App\\Dominio\\Pessoas\\Controllers"),
    "InstitutionalDocsController.php": ("Modulos/modelos-documentos/Controllers", "App\\Modulos\\ModelosDocumentos\\Controllers"),
    "UiModelosController.php": ("Modulos/modelos-documentos/Controllers", "App\\Modulos\\ModelosDocumentos\\Controllers"),
    # Painel shell / infra admin
    "AdminBaseController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "AdminCreditosController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "DevAdminController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "DevSettingsController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "DocSistemaController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "ReportAdminController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
}

FOLDER_DEFAULTS: dict[str, tuple[str, str]] = {
    "Master": ("Painel/Master/Controllers", "App\\Painel\\Master\\Controllers"),
    "Auth": ("Painel/Auth/Controllers", "App\\Painel\\Auth\\Controllers"),
    "Api": ("Painel/Api/Controllers", "App\\Painel\\Api\\Controllers"),
    "Media": ("Painel/Media/Controllers", "App\\Painel\\Media\\Controllers"),
    "Public": ("Painel/Public/Controllers", "App\\Painel\\Public\\Controllers"),
    "Pwa": ("Painel/Pwa/Controllers", "App\\Painel\\Pwa\\Controllers"),
    "Support": ("Painel/Support/Controllers", "App\\Painel\\Support\\Controllers"),
    "ExternalApps": ("Painel/ExternalApps/Controllers", "App\\Painel\\ExternalApps\\Controllers"),
    "Integrations": ("Painel/Integrations/Controllers", "App\\Painel\\Integrations\\Controllers"),
    "Webhooks": ("Painel/Webhooks/Controllers", "App\\Painel\\Webhooks\\Controllers"),
    "Games": ("Painel/Games/Controllers", "App\\Painel\\Games\\Controllers"),
    "Notes": ("Painel/Notes/Controllers", "App\\Painel\\Notes\\Controllers"),
    "Noticias": ("Painel/Noticias/Controllers", "App\\Painel\\Noticias\\Controllers"),
    "Declarations": ("Modulos/modelos-documentos/Controllers", "App\\Modulos\\ModelosDocumentos\\Controllers"),
    "EducaHits": ("Painel/EducaHits/Controllers", "App\\Painel\\EducaHits\\Controllers"),
    "EducaLabs": ("Painel/EducaLabs/Controllers", "App\\Painel\\EducaLabs\\Controllers"),
    "EducaInclui": ("Modulos/educainclui/Controllers", "App\\Modulos\\EducaInclui\\Controllers"),
    "Teacher": ("Painel/Professor/Controllers", "App\\Painel\\Professor\\Controllers"),
    "Student": ("Painel/Aluno/Controllers", "App\\Painel\\Aluno\\Controllers"),
    "User": ("Painel/User/Controllers", "App\\Painel\\User\\Controllers"),
    "Education": ("Dominio/Escola/Controllers", "App\\Dominio\\Escola\\Controllers"),
    "Admin": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
}

ROOT_CTRL = {
    "AIJobController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
    "MonitoramentoController.php": ("Painel/Admin/Controllers", "App\\Painel\\Admin\\Controllers"),
}

MODEL_MOVES: list[tuple[str, str, str]] = [
    ("Ava/CourseCategory.php", "Modulos/ava/Models", "App\\Modulos\\Ava\\Models"),
    ("Ava/Semester.php", "Modulos/ava/Models", "App\\Modulos\\Ava\\Models"),
    ("Forum/ForumModerationAlert.php", "Modulos/forum/Models", "App\\Modulos\\Forum\\Models"),
    ("Forum/ForumReply.php", "Modulos/forum/Models", "App\\Modulos\\Forum\\Models"),
    ("Forum/ForumReport.php", "Modulos/forum/Models", "App\\Modulos\\Forum\\Models"),
    ("Forum/ForumVote.php", "Modulos/forum/Models", "App\\Modulos\\Forum\\Models"),
    ("Study/Flashcard.php", "Modulos/flashcards/Models", "App\\Modulos\\Flashcards\\Models"),
    ("Study/FlashcardDeck.php", "Modulos/flashcards/Models", "App\\Modulos\\Flashcards\\Models"),
    ("Simulados/SimuladosQuestaoCategoria.php", "Modulos/provas/Models", "App\\Modulos\\Provas\\Models"),
    ("PushNotifications/PushNotification.php", "Modulos/notificacoes/Models", "App\\Modulos\\Notificacoes\\Models"),
    ("System/DevSetting.php", "Painel/Admin/Models", "App\\Painel\\Admin\\Models"),
    ("Noticia.php", "Painel/Noticias/Models", "App\\Painel\\Noticias\\Models"),
    ("NotificacaoApi.php", "Modulos/notificacoes/Models", "App\\Modulos\\Notificacoes\\Models"),
    ("EducaInclui/LaudoAluno.php", "Modulos/educainclui/Models", "App\\Modulos\\EducaInclui\\Models"),
    ("EducaInclui/MascaraAluno.php", "Modulos/educainclui/Models", "App\\Modulos\\EducaInclui\\Models"),
    ("EducaInclui/RegraMascara.php", "Modulos/educainclui/Models", "App\\Modulos\\EducaInclui\\Models"),
    ("EducaInclui/VersaoAdaptada.php", "Modulos/educainclui/Models", "App\\Modulos\\EducaInclui\\Models"),
    ("EducaInclui/VersaoAdaptadaLog.php", "Modulos/educainclui/Models", "App\\Modulos\\EducaInclui\\Models"),
    ("Enrollment/Enrollment.php", "Modulos/matricula/Models", "App\\Modulos\\Matricula\\Models"),
    ("Finance/FinanceConfig.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
    ("Finance/FinanceContract.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
    ("Finance/FinanceContractItem.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
    ("Finance/FinanceInstallment.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
    ("Finance/FinanceLedger.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
    ("Finance/FinancePlan.php", "Modulos/financeiro/Models", "App\\Modulos\\Financeiro\\Models"),
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


def is_shim(text: str) -> bool:
    head = text[:400]
    return "Shim →" in head or "vive em app/Modulos" in head


def resolve_ctrl_dst(rel: str) -> tuple[str, str] | None:
    """rel like Admin/Foo.php or Foo.php or Api/V1/Bar.php"""
    name = Path(rel).name
    if name in ROOT_CTRL:
        return ROOT_CTRL[name]
    if name in ADMIN_MAP and rel.startswith("Admin/"):
        return ADMIN_MAP[name]
    parts = Path(rel).parts
    if not parts:
        return None
    top = parts[0]
    if top in FOLDER_DEFAULTS:
        dst_dir, ns = FOLDER_DEFAULTS[top]
        # Api/V1 keeps subfolder
        if top == "Api" and len(parts) > 2:
            sub = "/".join(parts[1:-1])  # V1
            return (f"{dst_dir}/{sub}", ns + "\\" + sub.replace("/", "\\"))
        return (dst_dir, ns)
    if top == "Admin":
        return FOLDER_DEFAULTS["Admin"]
    return None


def move_controller(rel: Path) -> str | None:
    src = CTRL / rel
    if not src.exists():
        return None
    text = src.read_text(errors="replace")
    if is_shim(text):
        # already moved — keep only for deletion later
        return "shim"
    dst_info = resolve_ctrl_dst(str(rel).replace("\\", "/"))
    if not dst_info:
        print("NO MAP", rel)
        return None
    dst_dir_rel, new_ns = dst_info
    # fix Api\V1 ns if double escaped wrong
    new_ns = new_ns.replace("\\\\", "\\")
    short = rel.name
    dst = APP / dst_dir_rel / short
    dst.parent.mkdir(parents=True, exist_ok=True)
    if dst.exists() and not is_shim(dst.read_text(errors="replace")[:400]) and dst.stat().st_size > 800:
        # target already has code — drop src later
        print("EXISTS", rel, "→", dst_dir_rel)
        return "exists"
    dst.write_text(rewrite_ns(text, new_ns))
    print("MOVE", rel, "→", dst_dir_rel)
    return "moved"


def move_model(rel: str, dst_dir: str, new_ns: str) -> tuple[str, str, str] | None:
    """Returns (old_fqcn, new_fqcn, abs_path) for alias map."""
    src = MODELS / rel
    if not src.exists():
        return None
    text = src.read_text(errors="replace")
    if is_shim(text):
        # resolve target from shim for map
        m = re.search(r"require_once dirname\(__DIR__, \d+\) \. '/([^']+)'", text)
        am = re.search(r"class_alias\(\\([A-Za-z0-9\\]+)::class,\s*\\([A-Za-z0-9\\]+)::class", text)
        if m and am:
            return (am.group(2), am.group(1), str(APP / m.group(1)))
        return None
    short = Path(rel).stem
    m = re.search(r"namespace\s+([\w\\]+);", text)
    old_ns = m.group(1) if m else "App\\Models"
    old_fqcn = f"{old_ns}\\{short}"
    new_fqcn = f"{new_ns}\\{short}"
    dst = APP / dst_dir / f"{short}.php"
    dst.parent.mkdir(parents=True, exist_ok=True)
    if dst.exists() and not is_shim(dst.read_text(errors="replace")[:400]) and dst.stat().st_size > 500:
        print("MODEL EXISTS", rel)
        return (old_fqcn, new_fqcn, str(dst))
    dst.write_text(rewrite_ns(text, new_ns))
    print("MODEL", rel, "→", dst_dir)
    return (old_fqcn, new_fqcn, str(dst))


def collect_existing_model_aliases() -> list[tuple[str, str, str]]:
    """From current shims in Models/."""
    out = []
    for f in MODELS.rglob("*.php"):
        text = f.read_text(errors="replace")
        if not is_shim(text):
            continue
        m = re.search(r"require_once dirname\(__DIR__, \d+\) \. '/([^']+)'", text)
        # class_alias(\New::class, \Old::class)
        aliases = re.findall(
            r"class_alias\(\\(App\\[A-Za-z0-9\\]+)::class,\s*\\(App\\Models\\[A-Za-z0-9\\]+)::class\)",
            text,
        )
        if not m:
            continue
        path = str(APP / m.group(1))
        for new_fqcn, old_fqcn in aliases:
            out.append((old_fqcn, new_fqcn, path))
        if not aliases:
            # try any class_alias to App\Models
            aliases2 = re.findall(
                r"class_alias\(\\(App\\[A-Za-z0-9\\]+)::class,\s*\\(App\\[A-Za-z0-9\\]+)::class\)",
                text,
            )
            for new_fqcn, old_fqcn in aliases2:
                if old_fqcn.startswith("App\\Models"):
                    out.append((old_fqcn, new_fqcn, path))
    return out


def collect_all_moved_models() -> list[tuple[str, str, str]]:
    """Scan Dominio/Modulos/Painel Models for namespace + invent old App\Models FQCN when possible."""
    out = []
    for base in [APP / "Dominio", APP / "Modulos", APP / "Painel"]:
        if not base.exists():
            continue
        for f in base.rglob("Models/*.php"):
            text = f.read_text(errors="replace")
            m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
            cm = re.search(r"\bclass\s+(\w+)", text)
            if not m or not cm:
                continue
            new_fqcn = f"{m.group(1)}\\{cm.group(1)}"
            short = cm.group(1)
            # common old paths
            candidates = [
                f"App\\Models\\{short}",
                f"App\\Models\\Exams\\{short}",
                f"App\\Models\\Ava\\{short}",
                f"App\\Models\\Essays\\{short}",
                f"App\\Models\\Education\\{short}",
                f"App\\Models\\Forum\\{short}",
                f"App\\Models\\Study\\{short}",
                f"App\\Models\\User\\{short}",
                f"App\\Models\\System\\{short}",
                f"App\\Models\\Notifications\\{short}",
                f"App\\Models\\Simulados\\{short}",
                f"App\\Models\\Finance\\{short}",
                f"App\\Models\\Enrollment\\{short}",
                f"App\\Models\\EducaInclui\\{short}",
                f"App\\Models\\Bncc\\{short}",
                f"App\\Models\\PushNotifications\\{short}",
            ]
            # Prefer alias from Dominio Pessoas/Escola known
            for old in candidates:
                out.append((old, new_fqcn, str(f)))
    return out


def write_controllers_compat() -> None:
    """Mapa App\\Controllers\\* → arquivo real para class_alias."""
    entries: dict[str, tuple[str, str]] = {}
    for base_name, key_prefix in [
        ("Dominio", "Dominio"),
        ("Modulos", "Modulos"),
        ("Painel", "Painel"),
    ]:
        base = APP / base_name
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
            new_fqcn = f"{m.group(1)}\\{cm.group(1)}"
            short = cm.group(1)
            # aliases legados comuns
            olds = [
                f"App\\Controllers\\{short}",
                f"App\\Controllers\\Admin\\{short}",
                f"App\\Controllers\\Education\\{short}",
                f"App\\Controllers\\Master\\{short}",
                f"App\\Controllers\\Auth\\{short}",
                f"App\\Controllers\\User\\{short}",
                f"App\\Controllers\\Teacher\\{short}",
                f"App\\Controllers\\Student\\{short}",
                f"App\\Controllers\\Exams\\{short}",
                f"App\\Controllers\\Essays\\{short}",
                f"App\\Controllers\\Ava\\{short}",
                f"App\\Controllers\\Forum\\{short}",
                f"App\\Controllers\\Notifications\\{short}",
                f"App\\Controllers\\Exercises\\{short}",
                f"App\\Controllers\\Apostilas\\{short}",
                f"App\\Controllers\\Games\\{short}",
                f"App\\Controllers\\Integrations\\{short}",
                f"App\\Controllers\\Webhooks\\{short}",
                f"App\\Controllers\\Media\\{short}",
                f"App\\Controllers\\Declarations\\{short}",
                f"App\\Controllers\\EducaInclui\\{short}",
                f"App\\Controllers\\EducaHits\\{short}",
                f"App\\Controllers\\EducaLabs\\{short}",
                f"App\\Controllers\\Notes\\{short}",
                f"App\\Controllers\\Noticias\\{short}",
                f"App\\Controllers\\Public\\{short}",
                f"App\\Controllers\\Pwa\\{short}",
                f"App\\Controllers\\Support\\{short}",
                f"App\\Controllers\\ExternalApps\\{short}",
                f"App\\Controllers\\Study\\{short}",
                f"App\\Painel\\Admin\\Controllers\\{short}",
            ]
            # Api/V1
            if "Painel/Api/Controllers/V1" in rel or "Api/Controllers/V1" in rel:
                olds.append(f"App\\Controllers\\Api\\V1\\{short}")
            for old in olds:
                entries[old] = (new_fqcn, rel)

    compat = APP / "Compat"
    compat.mkdir(exist_ok=True)
    lines = ["<?php\n", "/** App\\Controllers\\* → [FQCN novo, path rel app/]. */\n", "return [\n"]
    for old in sorted(entries.keys()):
        new, rel = entries[old]
        lines.append(f"    '{old}' => ['{new}', '{rel}'],\n")
    lines.append("];\n")
    (compat / "controllers_map.php").write_text("".join(lines))
    (compat / "controllers_autoload.php").write_text(
        "<?php\n"
        "/** Autoload compatível para App\\Controllers\\* sem pasta app/Controllers. */\n"
        "spl_autoload_register(static function (string $class): void {\n"
        "    if (strncmp($class, 'App\\\\Controllers\\\\', 16) !== 0) {\n"
        "        return;\n"
        "    }\n"
        "    static $map = null;\n"
        "    if ($map === null) {\n"
        "        $map = require __DIR__ . '/controllers_map.php';\n"
        "    }\n"
        "    if (!isset($map[$class])) {\n"
        "        return;\n"
        "    }\n"
        "    [$newFqcn, $rel] = $map[$class];\n"
        "    $file = dirname(__DIR__) . '/' . $rel;\n"
        "    if (is_file($file)) {\n"
        "        require_once $file;\n"
        "    }\n"
        "    if (class_exists($newFqcn, false) && !class_exists($class, false)) {\n"
        "        class_alias($newFqcn, $class);\n"
        "    }\n"
        "}, true, true);\n"
    )
    print("Wrote Compat/controllers_map.php entries=", len(entries))


def write_models_map(entries: list[tuple[str, str, str]]) -> None:
    # old_fqcn → (new_fqcn, path relative to app)
    best: dict[str, tuple[str, str]] = {}
    for old, new, path in entries:
        p = Path(path)
        try:
            rel = p.relative_to(APP).as_posix()
        except ValueError:
            rel = path
        # prefer entry whose file exists and new ns matches file location
        if old not in best:
            best[old] = (new, rel)
        else:
            # prefer non-generic App\Models\X if more specific already there
            pass

    compat = APP / "Compat"
    compat.mkdir(exist_ok=True)
    lines = ["<?php\n", "/** Mapa App\\Models\\* → arquivo real (sem pasta Models/). */\n", "return [\n"]
    for old in sorted(best.keys()):
        new, rel = best[old]
        lines.append(f"    {old!r} => [{new!r}, {rel!r}],\n".replace("\\\\", "\\"))
    # fix quoting - use proper PHP
    lines = ["<?php\n", "/** Mapa App\\Models\\* → [FQCN novo, path relativo a app/]. */\n", "return [\n"]
    for old in sorted(best.keys()):
        new, rel = best[old]
        lines.append(f"    '{old}' => ['{new}', '{rel}'],\n")
    lines.append("];\n")
    (compat / "models_map.php").write_text("".join(lines))

    (compat / "models_autoload.php").write_text(
        "<?php\n"
        "/** Autoload compatível para App\\Models\\* sem pasta app/Models. */\n"
        "spl_autoload_register(static function (string $class): void {\n"
        "    if (strncmp($class, 'App\\\\Models\\\\', 11) !== 0) {\n"
        "        return;\n"
        "    }\n"
        "    static $map = null;\n"
        "    if ($map === null) {\n"
        "        $map = require __DIR__ . '/models_map.php';\n"
        "    }\n"
        "    if (!isset($map[$class])) {\n"
        "        return;\n"
        "    }\n"
        "    [$newFqcn, $rel] = $map[$class];\n"
        "    $file = dirname(__DIR__) . '/' . $rel;\n"
        "    if (is_file($file)) {\n"
        "        require_once $file;\n"
        "    }\n"
        "    if (class_exists($newFqcn, false) && !class_exists($class, false)) {\n"
        "        class_alias($newFqcn, $class);\n"
        "    }\n"
        "}, true, true);\n"
    )
    print("Wrote Compat/models_map.php entries=", len(best))


def main() -> None:
    moved = 0
    for f in sorted(CTRL.rglob("*.php")):
        rel = f.relative_to(CTRL)
        r = move_controller(rel)
        if r in ("moved", "exists", "shim"):
            moved += 1

    model_entries: list[tuple[str, str, str]] = []
    model_entries.extend(collect_existing_model_aliases())
    for rel, dst, ns in MODEL_MOVES:
        e = move_model(rel, dst, ns)
        if e:
            model_entries.append(e)
    # also scan already-moved models for map (may be noisy — filter to real old names from shims first)
    # Rebuild map primarily from shims + explicit moves; then add Dominio User models
    for f in (APP / "Dominio").rglob("Models/*.php") if (APP / "Dominio").exists() else []:
        text = f.read_text(errors="replace")
        m = re.search(r"namespace\s+(App\\[\w\\]+);", text)
        cm = re.search(r"\bclass\s+(\w+)", text)
        if not m or not cm:
            continue
        short = cm.group(1)
        new_fqcn = f"{m.group(1)}\\{short}"
        # Pessoas → User, Escola → Education
        if "Pessoas" in m.group(1):
            model_entries.append((f"App\\Models\\User\\{short}", new_fqcn, str(f)))
            model_entries.append((f"App\\Models\\{short}", new_fqcn, str(f)))
        if "Escola" in m.group(1):
            model_entries.append((f"App\\Models\\Education\\{short}", new_fqcn, str(f)))
            model_entries.append((f"App\\Models\\Bncc\\{short}", new_fqcn, str(f)))

    # From remaining shims after model moves
    model_entries.extend(collect_existing_model_aliases())
    write_models_map(model_entries)
    write_controllers_compat()
    print("DONE controllers processed≈", moved)


if __name__ == "__main__":
    main()
