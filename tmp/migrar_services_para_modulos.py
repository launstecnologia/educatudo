#!/usr/bin/env python3
"""
Opção A: move Services/ → Modulos/{slug}/Services ou Dominio (código único + shim).
"""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"

# filename → (destino relativo a app/, new namespace)
MOVES: list[tuple[str, str, str]] = [
    # provas
    ("ExamBlockResultsDashboardService.php", "Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    ("ExamImageService.php", "Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    # ava
    ("AvaActivityService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaAgendaService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaCertificateService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaCourseService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaEnrollmentService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaEvaluationService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaLiveClassService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    ("AvaProgressService.php", "Modulos/ava/Services", "App\\Modulos\\Ava\\Services"),
    # redacoes
    ("EssayAIService.php", "Modulos/redacoes/Services", "App\\Modulos\\Redacoes\\Services"),
    ("EssayOCRService.php", "Modulos/redacoes/Services", "App\\Modulos\\Redacoes\\Services"),
    # forum
    ("ForumSecurityService.php", "Modulos/forum/Services", "App\\Modulos\\Forum\\Services"),
    # flashcards
    ("FlashcardService.php", "Modulos/flashcards/Services", "App\\Modulos\\Flashcards\\Services"),
    # boletins
    ("BoletimAssistenteFerramentas.php", "Modulos/boletins/Services", "App\\Modulos\\Boletins\\Services"),
    ("BoletimAssistenteService.php", "Modulos/boletins/Services", "App\\Modulos\\Boletins\\Services"),
    ("BoletimAssistenteWizard.php", "Modulos/boletins/Services", "App\\Modulos\\Boletins\\Services"),
    ("BoletimConsultaAssistenteService.php", "Modulos/boletins/Services", "App\\Modulos\\Boletins\\Services"),
    ("BoletimGeradosExportacao.php", "Modulos/boletins/Services", "App\\Modulos\\Boletins\\Services"),
    # financeiro (se ainda em Services/)
    ("FinanceBillingService.php", "Modulos/financeiro/Services", "App\\Modulos\\Financeiro\\Services"),
    ("FinanceBoletoService.php", "Modulos/financeiro/Services", "App\\Modulos\\Financeiro\\Services"),
    ("FinanceContractService.php", "Modulos/financeiro/Services", "App\\Modulos\\Financeiro\\Services"),
    ("FinanceLedgerService.php", "Modulos/financeiro/Services", "App\\Modulos\\Financeiro\\Services"),
    ("FinancePlanService.php", "Modulos/financeiro/Services", "App\\Modulos\\Financeiro\\Services"),
    # apostilas
    ("ApostilaIaClientException.php", "Modulos/apostilas/Services", "App\\Modulos\\Apostilas\\Services"),
    ("ApostilaIaClientService.php", "Modulos/apostilas/Services", "App\\Modulos\\Apostilas\\Services"),
    ("ApostilaIaSessaoService.php", "Modulos/apostilas/Services", "App\\Modulos\\Apostilas\\Services"),
    # conformidade
    ("ComplianceAIService.php", "Modulos/conformidade/Services", "App\\Modulos\\Conformidade\\Services"),
    ("ComplianceService.php", "Modulos/conformidade/Services", "App\\Modulos\\Conformidade\\Services"),
    # ocorrencias
    ("OcorrenciaService.php", "Modulos/ocorrencias/Services", "App\\Modulos\\Ocorrencias\\Services"),
    # dominio escola
    # Bncc already moved
]

# Models Bncc → Dominio
MODEL_MOVES: list[tuple[str, str, str]] = [
    ("Bncc/BnccSkill.php", "Dominio/Escola/Models", "App\\Dominio\\Escola\\Models"),
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


def move_one(src: Path, dst_dir_rel: str, new_ns: str, depth: int) -> bool:
    if not src.exists():
        print("skip missing", src.name)
        return False
    text = src.read_text()
    if "Shim →" in text:
        print("already shim", src.name)
        return False
    # avoid overwriting existing real target with different content
    short = src.name
    dst = APP / dst_dir_rel / short
    new_fqcn = f"{new_ns}\\{short.replace('.php', '')}"
    m = re.search(r"namespace\s+([\w\\]+);", text)
    old_ns = m.group(1) if m else "App\\Services"
    old_fqcn = f"{old_ns}\\{short.replace('.php', '')}"

    if dst.exists() and "Shim →" not in dst.read_text()[:200]:
        # target already has real code — only leave shim at src if content same-ish
        print("target exists, shim only", src.name, "→", dst)
        write_shim(src, f"{dst_dir_rel}/{short}", new_fqcn, old_fqcn, depth)
        return True

    dst.parent.mkdir(parents=True, exist_ok=True)
    dst.write_text(rewrite_ns(text, new_ns))
    write_shim(src, f"{dst_dir_rel}/{short}", new_fqcn, old_fqcn, depth)
    print("OK", src.name, "→", dst_dir_rel)
    return True


def main() -> None:
    n = 0
    for name, dst_rel, new_ns in MOVES:
        if move_one(APP / "Services" / name, dst_rel, new_ns, depth=1):
            n += 1
    for rel, dst_rel, new_ns in MODEL_MOVES:
        src = APP / "Models" / rel
        depth = 1 + rel.count("/")
        if move_one(src, dst_rel, new_ns, depth=depth):
            n += 1
    print(f"DONE n={n}")


if __name__ == "__main__":
    main()
