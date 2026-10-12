#!/usr/bin/env python3
"""
Elimina app/Services/: move cada serviço real para Dominio / Modulos / Painel / Infra.
Gera Compat/services_map.php + services_autoload.php.
"""
from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
APP = ROOT / "backend/app"
SVC = APP / "Services"

# filename → (dst_dir_rel, new_ns)
MOVES: dict[str, tuple[str, str]] = {
    # Domínio Escola
    "MatrizCurricularService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    "SalaService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    "SchoolCalendarService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    "DominioEscolaService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    "SaudeAcademicaService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    "ImplantacaoAcademicaService.php": ("Dominio/Escola/Services", "App\\Dominio\\Escola\\Services"),
    # Domínio Pessoas
    "StudentResponsibleImportService.php": ("Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services"),
    "ParentAcademicReadService.php": ("Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services"),
    "ParentJornadasService.php": ("Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services"),
    "StudentAgendaService.php": ("Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services"),
    "DashboardAlunoService.php": ("Dominio/Pessoas/Services", "App\\Dominio\\Pessoas\\Services"),
    # Módulos
    "ClassDiaryService.php": ("Modulos/diario/Services", "App\\Modulos\\Diario\\Services"),
    "ClassDiaryReportService.php": ("Modulos/diario/Services", "App\\Modulos\\Diario\\Services"),
    "ConselhoService.php": ("Modulos/conselho-classe/Services", "App\\Modulos\\ConselhoClasse\\Services"),
    "AulaOnlineGravacaoService.php": ("Modulos/aulas-online/Services", "App\\Modulos\\AulasOnline\\Services"),
    "JaasMeetService.php": ("Modulos/aulas-online/Services", "App\\Modulos\\AulasOnline\\Services"),
    "PandaVideoLiveService.php": ("Modulos/aulas-online/Services", "App\\Modulos\\AulasOnline\\Services"),
    "CustomExerciseImportService.php": ("Modulos/exercicios/Services", "App\\Modulos\\Exercicios\\Services"),
    "EducaIncluiService.php": ("Modulos/educainclui/Services", "App\\Modulos\\EducaInclui\\Services"),
    "MascaraResolver.php": ("Modulos/educainclui/Services", "App\\Modulos\\EducaInclui\\Services"),
    "EnrollmentService.php": ("Modulos/matricula/Services", "App\\Modulos\\Matricula\\Services"),
    "EnrollmentScoreService.php": ("Modulos/matricula/Services", "App\\Modulos\\Matricula\\Services"),
    "MatriculaSchemaHelper.php": ("Modulos/matricula/Services", "App\\Modulos\\Matricula\\Services"),
    "FrequencyService.php": ("Modulos/presenca/Services", "App\\Modulos\\Presenca\\Services"),
    "FacialAttendanceNotificationService.php": ("Modulos/presenca/Services", "App\\Modulos\\Presenca\\Services"),
    "FacialRecognitionProviderService.php": ("Modulos/presenca/Services", "App\\Modulos\\Presenca\\Services"),
    "HistoricoEscolarService.php": ("Modulos/modelos-documentos/Services", "App\\Modulos\\ModelosDocumentos\\Services"),
    "DeclarationService.php": ("Modulos/modelos-documentos/Services", "App\\Modulos\\ModelosDocumentos\\Services"),
    "DocumentoOficialService.php": ("Modulos/modelos-documentos/Services", "App\\Modulos\\ModelosDocumentos\\Services"),
    "JornadasAlunoConsultaService.php": ("Modulos/jornadas/Services", "App\\Modulos\\Jornadas\\Services"),
    "JornadasRelatorioService.php": ("Modulos/jornadas/Services", "App\\Modulos\\Jornadas\\Services"),
    "ProvasAlunoAssistenteService.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "ProvasAlunoConsultaService.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "ProvasProfessorConsultaService.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "MasterProvasAoVivoService.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "EnemToSimuladosImportService.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "AssessmentHasher.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "AssessmentVersionGenerator.php": ("Modulos/provas/Services", "App\\Modulos\\Provas\\Services"),
    "RegraAcademicaService.php": ("Modulos/regras-academicas/Services", "App\\Modulos\\RegrasAcademicas\\Services"),
    "TipoNotaRegraService.php": ("Modulos/regras-academicas/Services", "App\\Modulos\\RegrasAcademicas\\Services"),
    "ResultadoAcademicoService.php": ("Modulos/fechamento/Services", "App\\Modulos\\Fechamento\\Services"),
    "ResultadoHomologacaoService.php": ("Modulos/fechamento/Services", "App\\Modulos\\Fechamento\\Services"),
    "SchoolCommunicationService.php": ("Modulos/comunicacao/Services", "App\\Modulos\\Comunicacao\\Services"),
    "SaudeAprendizagemService.php": ("Modulos/vida-escolar/Services", "App\\Modulos\\VidaEscolar\\Services"),
    "PendencyService.php": ("Modulos/vida-escolar/Services", "App\\Modulos\\VidaEscolar\\Services"),
    # Painel / Master / Admin
    "AssistenteConsultaAmpliadaService.php": ("Painel/Admin/Services", "App\\Painel\\Admin\\Services"),
    "AssistenteHistoricoService.php": ("Painel/Admin/Services", "App\\Painel\\Admin\\Services"),
    "DocSistemaWikiService.php": ("Painel/Admin/Services", "App\\Painel\\Admin\\Services"),
    "ImagemSliderService.php": ("Painel/Admin/Services", "App\\Painel\\Admin\\Services"),
    "ClonarEscolaService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterAvatarService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterCreditosCatalogoSyncService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterDashboardKpisService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterFilaIaService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterFilaService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterLlmCustosService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    "MasterMensalidadeService.php": ("Painel/Master/Services", "App\\Painel\\Master\\Services"),
    # Infra compartilhada
    "AIJobService.php": ("Infra/Services", "App\\Infra\\Services"),
    "BackupService.php": ("Infra/Services", "App\\Infra\\Services"),
    "ChatSafetyMonitorService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosAsaasCancelarPendentesService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosAsaasReconcileService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosCheckoutToken.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosPaymentFulfillmentService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosPurchaseNotifier.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CreditosTenantCheckoutHelper.php": ("Infra/Services", "App\\Infra\\Services"),
    "TenantCreditsCheckoutService.php": ("Infra/Services", "App\\Infra\\Services"),
    "CronExecucaoService.php": ("Infra/Services", "App\\Infra\\Services"),
    "DatabaseWrapper.php": ("Infra/Services", "App\\Infra\\Services"),
    "DocumentProcessorService.php": ("Infra/Services", "App\\Infra\\Services"),
    "EducaProfApiService.php": ("Infra/Services", "App\\Infra\\Services"),
    "EducaShopService.php": ("Infra/Services", "App\\Infra\\Services"),
    "ElevenLabsService.php": ("Infra/Services", "App\\Infra\\Services"),
    "EmailService.php": ("Infra/Services", "App\\Infra\\Services"),
    "EmbeddingService.php": ("Infra/Services", "App\\Infra\\Services"),
    "EvolutionApiService.php": ("Infra/Services", "App\\Infra\\Services"),
    "FileProcessorService.php": ("Infra/Services", "App\\Infra\\Services"),
    "FirebaseMessagingService.php": ("Infra/Services", "App\\Infra\\Services"),
    "JWTService.php": ("Infra/Services", "App\\Infra\\Services"),
    "LogsAplicacaoService.php": ("Infra/Services", "App\\Infra\\Services"),
    "MediaStorageService.php": ("Infra/Services", "App\\Infra\\Services"),
    "MetricsService.php": ("Infra/Services", "App\\Infra\\Services"),
    "MobileDeviceService.php": ("Infra/Services", "App\\Infra\\Services"),
    "NanoBananaService.php": ("Infra/Services", "App\\Infra\\Services"),
    "OneSignalService.php": ("Infra/Services", "App\\Infra\\Services"),
    "OpenAIService.php": ("Infra/Services", "App\\Infra\\Services"),
    "PdfTextExtractorService.php": ("Infra/Services", "App\\Infra\\Services"),
    "RAGService.php": ("Infra/Services", "App\\Infra\\Services"),
    "RssService.php": ("Infra/Services", "App\\Infra\\Services"),
    "SerpApiService.php": ("Infra/Services", "App\\Infra\\Services"),
    "TudinhaService.php": ("Infra/Services", "App\\Infra\\Services"),
    # Asaas client
    "Asaas/AsaasApiClient.php": ("Infra/Services/Asaas", "App\\Infra\\Services\\Asaas"),
}


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
    return "Shim →" in text[:500]


def php_quote(s: str) -> str:
    return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"


def move_one(rel: str, dst_dir: str, new_ns: str) -> tuple[str, str, str] | None:
    src = SVC / rel
    if not src.exists():
        print("skip missing", rel)
        return None
    text = src.read_text(errors="replace")
    short = Path(rel).name.replace(".php", "")
    if "Asaas/" in rel.replace("\\", "/"):
        old_fqcn = f"App\\Services\\Asaas\\{short}"
    else:
        old_fqcn = f"App\\Services\\{short}"

    new_fqcn = f"{new_ns}\\{short}"
    dst = APP / dst_dir / Path(rel).name
    dst.parent.mkdir(parents=True, exist_ok=True)

    if is_shim(text):
        print("shim skip", rel)
        return None

    if dst.exists() and not is_shim(dst.read_text(errors="replace")[:400]) and dst.stat().st_size > 500:
        # prefer existing module copy (already modularized)
        print("EXISTS keep-target", rel, "→", dst_dir)
        tt = dst.read_text(errors="replace")
        tm = re.search(r"namespace\s+([\w\\]+);", tt)
        if tm:
            new_fqcn = f"{tm.group(1)}\\{short}"
        return (old_fqcn, new_fqcn, str(dst))

    dst.write_text(rewrite_ns(text, new_ns))
    print("MOVE", rel, "→", dst_dir)
    return (old_fqcn, new_fqcn, str(dst))


def write_services_compat(entries: list[tuple[str, str, str]]) -> None:
    best: dict[str, tuple[str, str]] = {}
    for old, new, path in entries:
        if not old or not new:
            continue
        p = Path(path)
        try:
            rel = p.relative_to(APP).as_posix()
        except ValueError:
            continue
        best[old] = (new, rel)
        # also short App\Services\Name for Asaas nested already handled

    # scan all Services dirs for completeness
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
            old = "App\\Services\\" + short
            if "Asaas" in m.group(1):
                old = "App\\Services\\Asaas\\" + short
            if old not in best:
                best[old] = (new_fqcn, rel)

    compat = APP / "Compat"
    compat.mkdir(exist_ok=True)
    lines = ["<?php", "/** App\\Services\\* → [FQCN novo, path rel app/]. */", "return ["]
    for old in sorted(best):
        new, rel = best[old]
        lines.append(f"    {php_quote(old)} => [{php_quote(new)}, {php_quote(rel)}],")
    lines.append("];")
    lines.append("")
    (compat / "services_map.php").write_text("\n".join(lines))

    (compat / "services_autoload.php").write_text(
        "<?php\n"
        "/** Autoload compatível para App\\Services\\* sem pasta app/Services. */\n"
        "spl_autoload_register(static function (string $class): void {\n"
        "    if (strncmp($class, 'App\\\\Services\\\\', 13) !== 0) {\n"
        "        return;\n"
        "    }\n"
        "    static $map = null;\n"
        "    if ($map === null) {\n"
        "        $map = require __DIR__ . '/services_map.php';\n"
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
    print("Wrote services_map entries=", len(best))


def main() -> None:
    entries: list[tuple[str, str, str]] = []
    for rel, (dst, ns) in MOVES.items():
        e = move_one(rel, dst, ns)
        if e and e[1]:
            entries.append(e)  # type: ignore

    # any leftover real files not in map?
    for f in sorted(SVC.rglob("*.php")):
        rel = f.relative_to(SVC).as_posix()
        if rel in MOVES:
            continue
        text = f.read_text(errors="replace")
        if is_shim(text):
            continue
        print("UNMAPPED REAL", rel)

    write_services_compat(entries)
    print("DONE")


if __name__ == "__main__":
    main()
