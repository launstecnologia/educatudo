#!/usr/bin/env python3
"""Fecha leftovers de matrícula e inicia migração de boletim-configuracao."""
from pathlib import Path
import json
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
ADMIN = ROOT / "backend/config/routes/admin.php"


def strip_enrollment() -> None:
    text = ADMIN.read_text()
    lines = text.splitlines(keepends=True)
    out = []
    i = 0
    while i < len(lines):
        line = lines[i]
        if line.startswith("// Módulo de Matrículas e Rematrículas") or (
            line.strip().startswith("$router->") and "/admin/enrollment" in line
        ):
            if line.startswith("// Módulo de Matrículas"):
                out.append("// Matrículas → Modulos/matricula/routes.php\n")
                i += 1
            # skip contiguous enrollment routes
            while i < len(lines) and (
                (lines[i].strip().startswith("$router->") and "/admin/enrollment" in lines[i])
                or (
                    lines[i].strip() == ""
                    and i + 1 < len(lines)
                    and "/admin/enrollment" in lines[i + 1]
                )
            ):
                i += 1
            continue
        out.append(line)
        i += 1
    ADMIN.write_text("".join(out))
    left = len(re.findall(r"\$router->.*/admin/enrollment", ADMIN.read_text()))
    print(f"enrollment router left: {left}")


def patch_composer() -> None:
    cp = ROOT / "backend/composer.json"
    data = json.loads(cp.read_text())
    psr4 = data.setdefault("autoload", {}).setdefault("psr-4", {})
    psr4["App\\Modulos\\Matricula\\"] = "app/Modulos/matricula/"
    psr4["App\\Modulos\\Boletins\\"] = "app/Modulos/boletins/"
    cp.write_text(json.dumps(data, indent=4, ensure_ascii=False) + "\n")
    print("composer Matricula+Boletins OK")


BOLETIM_CONTROLLERS = [
    "BoletimConfigController.php",
    "BoletimGuiaController.php",
    "BoletimAssistenteController.php",
]


def move_boletim_controllers() -> None:
    src_dir = ROOT / "backend/app/Controllers/Admin"
    dst_dir = ROOT / "backend/app/Modulos/boletins/Controllers"
    dst_dir.mkdir(parents=True, exist_ok=True)

    for name in BOLETIM_CONTROLLERS:
        src = src_dir / name
        dst = dst_dir / name
        if not src.exists():
            if dst.exists():
                print(f"{name}: already in module")
                continue
            raise SystemExit(f"missing {src}")
        content = src.read_text()
        # namespace Admin -> Modulos\Boletins\Controllers
        content2 = content.replace(
            "namespace App\\Controllers\\Admin;",
            "namespace App\\Modulos\\Boletins\\Controllers;",
            1,
        )
        if content2 == content and "namespace App\\Modulos\\Boletins\\Controllers;" not in content:
            # maybe no namespace yet
            if not content.lstrip().startswith("<?php"):
                raise SystemExit(f"unexpected start in {name}")
            # insert after <?php
            content2 = re.sub(
                r"^<\?php\s*",
                "<?php\n\nnamespace App\\Modulos\\Boletins\\Controllers;\n\n",
                content,
                count=1,
            )
        dst.write_text(content2)
        # shim
        short = name.replace(".php", "")
        shim = (
            "<?php\n"
            "/**\n"
            " * Shim — controller vive em app/Modulos/boletins/.\n"
            " */\n"
            f"require_once dirname(__DIR__, 2) . '/Modulos/boletins/Controllers/{name}';\n"
            f"if (!class_exists('{short}', false)) {{\n"
            f"    class_alias(\\App\\Modulos\\Boletins\\Controllers\\{short}::class, '{short}');\n"
            "}\n"
        )
        src.write_text(shim)
        print(f"moved {name}")


def append_boletim_routes() -> None:
    routes = ROOT / "backend/app/Modulos/boletins/routes.php"
    text = routes.read_text()
    if "boletim-configuracao" in text:
        print("boletim routes already present")
        return

    block = r'''
// Listagem / arquivo / geração
$router->get('/admin/boletim', 'Modulos/boletins/BoletimConfigController@listagem');
$router->get('/admin/boletim/arquivo', 'Modulos/boletins/BoletimConfigController@arquivo');
$router->get('/admin/boletim/geracao-status', 'Modulos/boletins/BoletimConfigController@geracaoStatusJson');
$router->post('/admin/boletim/cancelar-geracao', 'Modulos/boletins/BoletimConfigController@cancelarGeracaoBoletim');
$router->get('/admin/boletim-guia', 'Modulos/boletins/BoletimGuiaController@index');

// Configuração e assistente
$router->get('/admin/boletim-configuracao', 'Modulos/boletins/BoletimConfigController@index');
$router->get('/admin/boletim-configuracao/assistente', 'Modulos/boletins/BoletimConfigController@assistente');
$router->get('/admin/boletim-configuracao/jornadas', 'Modulos/boletins/BoletimConfigController@jornadasJson');
$router->get('/admin/boletim-configuracao/evento-componentes', 'Modulos/boletins/BoletimConfigController@eventoComponentesJson');
$router->get('/admin/boletim-configuracao/keepalive', 'Modulos/boletins/BoletimConfigController@keepalive');
$router->post('/admin/boletim-configuracao/salvar', 'Modulos/boletins/BoletimConfigController@salvarRegra');
$router->post('/admin/boletim-configuracao/restaurar-config-versao', 'Modulos/boletins/BoletimConfigController@restaurarConfigVersao');
$router->post('/admin/boletim-configuracao/renomear', 'Modulos/boletins/BoletimConfigController@renomearRegra');
$router->post('/admin/boletim-configuracao/excluir-regra', 'Modulos/boletins/BoletimConfigController@excluirRegra');
$router->post('/admin/boletim-configuracao/duplicar-regra', 'Modulos/boletins/BoletimConfigController@duplicarRegra');
$router->post('/admin/boletim-configuracao/visibilidade-regra', 'Modulos/boletins/BoletimConfigController@alternarVisibilidadeRegra');
$router->post('/admin/boletim-configuracao/desabilitar-lista', 'Modulos/boletins/BoletimConfigController@desabilitarListaAvaliacoes');
$router->post('/admin/boletim-configuracao/notas-manuais', 'Modulos/boletins/BoletimConfigController@salvarNotasManuais');
$router->post('/admin/boletim-configuracao/nota-manual-materia-ajax', 'Modulos/boletins/BoletimConfigController@salvarNotaManualMateriaAjax');
$router->post('/admin/boletim-configuracao/gerar-boletins', 'Modulos/boletins/BoletimConfigController@gerarBoletins');
$router->post('/admin/boletim-configuracao/sincronizar-vida-escolar-salvos', 'Modulos/boletins/BoletimConfigController@sincronizarVidaEscolarSalvos');
$router->get('/admin/boletim-configuracao/checklist-pre-geracao', 'Modulos/boletins/BoletimConfigController@checklistPreGeracao');
$router->get('/admin/boletim-configuracao/logs-geracao', 'Modulos/boletins/BoletimConfigController@logsGeracaoJson');
$router->get('/admin/boletim-configuracao/geracao-detalhe', 'Modulos/boletins/BoletimConfigController@geracaoDetalheJson');
$router->post('/admin/boletim-configuracao/restaurar-geracao', 'Modulos/boletins/BoletimConfigController@restaurarGeracao');
$router->post('/admin/boletim-configuracao/travar-aluno', 'Modulos/boletins/BoletimConfigController@travarAluno');
$router->post('/admin/boletim-configuracao/destravar-aluno', 'Modulos/boletins/BoletimConfigController@destravarAluno');
$router->post('/admin/boletim-configuracao/simular-lote', 'Modulos/boletins/BoletimConfigController@simularLote');
$router->post('/admin/boletim-configuracao/publicar-boletim-aluno', 'Modulos/boletins/BoletimConfigController@publicarBoletimAlunoSimulado');
$router->post('/admin/boletim-configuracao/atualizar-boletins-gravados', 'Modulos/boletins/BoletimConfigController@atualizarBoletinsGravados');
$router->get('/admin/boletim-configuracao/gerados', 'Modulos/boletins/BoletimConfigController@boletinsGerados');
$router->get('/admin/boletim-configuracao/gerados/exportar', 'Modulos/boletins/BoletimConfigController@exportarBoletinsGerados');
$router->get('/admin/boletim-configuracao/gerados/preview', 'Modulos/boletins/BoletimConfigController@boletimGeradoPreview');
$router->post('/admin/boletim-configuracao/gerados/excluir', 'Modulos/boletins/BoletimConfigController@excluirBoletimGeradoAdmin');
$router->post('/admin/boletim-configuracao/gerados/excluir-lote', 'Modulos/boletins/BoletimConfigController@excluirBoletimGeradoLote');
$router->get('/admin/boletim-configuracao/assistente/contexto', 'Modulos/boletins/BoletimAssistenteController@contexto');
$router->post('/admin/boletim-configuracao/assistente/mensagem', 'Modulos/boletins/BoletimAssistenteController@mensagem');
$router->post('/admin/boletim-configuracao/assistente/mensagem-stream', 'Modulos/boletins/BoletimAssistenteController@mensagemStream');
$router->post('/admin/boletim-configuracao/assistente/ferramenta', 'Modulos/boletins/BoletimAssistenteController@ferramenta');
$router->post('/admin/boletim-configuracao/assistente/wizard/inicio', 'Modulos/boletins/BoletimAssistenteController@wizardInicio');
$router->post('/admin/boletim-configuracao/assistente/wizard/montar', 'Modulos/boletins/BoletimAssistenteController@wizardMontar');
$router->post('/admin/boletim-configuracao/assistente/consulta', 'Modulos/boletins/BoletimAssistenteController@consulta');
'''
    routes.write_text(text.rstrip() + "\n" + block)
    print("boletim routes appended")


def strip_boletim_from_admin() -> None:
    text = ADMIN.read_text()
    lines = text.splitlines(keepends=True)
    out = []
    i = 0
    prefixes = (
        "/admin/boletim'",
        '/admin/boletim"',
        "/admin/boletim/",
        "/admin/boletim-guia",
        "/admin/boletim-configuracao",
    )
    wrote = False
    while i < len(lines):
        line = lines[i]
        is_boletim_route = line.strip().startswith("$router->") and any(p in line for p in prefixes)
        # avoid students boletim and reports
        if is_boletim_route and "/admin/students/" in line:
            is_boletim_route = False
        if is_boletim_route and "reports/boletim" in line:
            is_boletim_route = False
        if is_boletim_route:
            if not wrote:
                if out and out[-1].strip().startswith("//") and "boletim" in out[-1].lower():
                    out[-1] = "// Boletim (config/guia/assistente) → Modulos/boletins/routes.php\n"
                else:
                    out.append("// Boletim (config/guia/assistente) → Modulos/boletins/routes.php\n")
                wrote = True
            i += 1
            continue
        out.append(line)
        i += 1
    ADMIN.write_text("".join(out))
    left = len(re.findall(r"\$router->.*/admin/boletim(-configuracao|-guia)?", ADMIN.read_text()))
    # exclude students
    left2 = 0
    for m in re.finditer(r"\$router->.*", ADMIN.read_text()):
        s = m.group(0)
        if "/admin/students/" in s or "reports/boletim" in s:
            continue
        if "/admin/boletim" in s:
            left2 += 1
    print(f"boletim router left (non-student): {left2}")


if __name__ == "__main__":
    strip_enrollment()
    patch_composer()
    move_boletim_controllers()
    append_boletim_routes()
    strip_boletim_from_admin()
