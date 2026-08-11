<?php

namespace App\Services;

use App\Models\Enrollment\Enrollment;
use Database;

class EnrollmentService
{
    private $db;
    private Enrollment $model;

    public function __construct(?Database $db = null)
    {
        $this->db    = $db ?? Database::getInstance();
        $this->model = new Enrollment($this->db);
    }

    // ── Busca dados de aluno existente para pré-preencher rematrícula ──────────

    public function prefillFromAluno(int $alunoId): array
    {
        $aluno = $this->db->fetch(
            "SELECT a.*, t.nome AS turma_nome, t.serie AS turma_serie, t.id AS turma_id_atual
             FROM alunos a
             LEFT JOIN turmas t ON t.id = a.turma_id
             WHERE a.id = ?",
            [$alunoId]
        );
        if (!$aluno) return [];

        $resp = $this->db->fetch(
            "SELECT r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo
             FROM alunos_responsaveis ar
             INNER JOIN responsaveis r ON r.id = ar.responsavel_id
             WHERE ar.aluno_id = ? AND ar.is_financeiro = 1
             LIMIT 1",
            [$alunoId]
        ) ?: $this->db->fetch(
            "SELECT r.nome, r.cpf, r.email, r.telefone, ar.tipo_vinculo
             FROM alunos_responsaveis ar
             INNER JOIN responsaveis r ON r.id = ar.responsavel_id
             WHERE ar.aluno_id = ?
             ORDER BY ar.id ASC LIMIT 1",
            [$alunoId]
        ) ?: [];

        // Anos na escola (para cálculo de fidelidade no score)
        $anosNaEscola = (int) ($this->db->fetch(
            "SELECT COUNT(DISTINCT ano_letivo_id) AS total FROM matricula WHERE aluno_id = ?",
            [$alunoId]
        )['total'] ?? 1);

        return [
            'aluno_id'        => $alunoId,
            'aluno_nome'      => $aluno['nome'] ?? '',
            'aluno_cpf'       => $aluno['cpf'] ?? '',
            'aluno_data_nasc' => $aluno['data_nasc'] ?? null,
            'aluno_genero'    => $aluno['genero'] ?? null,
            'aluno_email'     => $aluno['email'] ?? null,
            'aluno_telefone'  => $aluno['telefone'] ?? null,
            'resp_nome'       => $resp['nome'] ?? '',
            'resp_cpf'        => $resp['cpf'] ?? '',
            'resp_email'      => $resp['email'] ?? '',
            'resp_telefone'   => $resp['telefone'] ?? '',
            'resp_parentesco' => $resp['tipo_vinculo'] ?? '',
            'turma_id_atual'  => $aluno['turma_id_atual'] ?? null,
            'turma_nome'      => $aluno['turma_nome'] ?? '',
            'anos_na_escola'  => $anosNaEscola,
        ];
    }

    // ── Gera token único para o contrato ─────────────────────────────────────

    public function generateContratoToken(int $enrollmentId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->model->update($enrollmentId, ['contrato_token' => $token]);
        return $token;
    }

    // ── Gera PDF do contrato via Dompdf ──────────────────────────────────────

    public function gerarContratoPDF(array $enrollment, array $escola): string
    {
        $prevErrorReporting = error_reporting(E_ERROR | E_WARNING | E_PARSE);
        $prevDisplayErrors  = ini_get('display_errors');
        ini_set('display_errors', '0');
        // Load financial contract linked to this enrollment
        $contrato = $this->db->fetch(
            "SELECT fc.*, r.nome AS responsavel_nome_full, r.cpf AS responsavel_cpf_full
             FROM finance_contracts fc
             LEFT JOIN responsaveis r ON r.id = fc.responsavel_id
             WHERE fc.enrollment_id = ? OR (fc.aluno_id = ? AND fc.ano_letivo_id = ?)
             ORDER BY fc.id DESC LIMIT 1",
            [$enrollment['id'], $enrollment['aluno_id'] ?? 0, $enrollment['ano_letivo_id'] ?? 0]
        ) ?: [];

        $itens = [];
        if (!empty($contrato['id'])) {
            $itens = $this->db->fetchAll(
                "SELECT * FROM finance_contract_items WHERE contract_id = ? AND status = 'ativo' ORDER BY id",
                [$contrato['id']]
            ) ?: [];
        }

        $html = $this->renderContratoHtml($enrollment, $escola, $contrato, $itens);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $slug    = preg_replace('/[^a-z0-9]/i', '_', substr($enrollment['aluno_nome'], 0, 30));
        $dir     = __DIR__ . '/../../storage/enrollments/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'contrato_' . $enrollment['id'] . '_' . $slug . '_' . date('Ymd_His') . '.pdf';
        $path     = $dir . $filename;
        file_put_contents($path, $dompdf->output());

        $hash = hash('sha256', $dompdf->output());

        $this->model->update($enrollment['id'], [
            'contrato_pdf_path' => 'storage/enrollments/' . $filename,
            'contrato_hash'     => $hash,
            'status'            => 'aguardando_assinatura',
        ]);

        error_reporting($prevErrorReporting);
        ini_set('display_errors', $prevDisplayErrors);

        return 'storage/enrollments/' . $filename;
    }

    private function renderContratoHtml(array $e, array $escola, array $contrato = [], array $itens = []): string
    {
        $esc   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $brl   = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $fmtDt = function ($d) {
            if (!$d || $d === '0000-00-00') return '—';
            $dt = \DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
            return $dt ? $dt->format('d/m/Y') : '—';
        };
        $tipoLabel  = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
        $catLabel   = ['mensalidade' => 'Mensalidade', 'matricula' => 'Taxa de Matrícula',
                       'material_didatico' => 'Material Didático', 'uniforme' => 'Uniforme',
                       'taxa' => 'Taxa', 'outros' => 'Outros'];
        $tipo       = $tipoLabel[$e['tipo'] ?? 'nova'] ?? 'Matrícula';
        $nomeEscola = $escola['nome']     ?? (defined('TENANT_SLUG') ? TENANT_SLUG : 'Escola');
        $logoUrl    = $escola['logo_url'] ?? '';
        $cnpj       = $escola['cnpj']     ?? '';
        $endEscola  = trim(($escola['endereco'] ?? '') . ' ' . ($escola['cidade'] ?? '') . ' ' . ($escola['uf'] ?? ''));
        $hoje       = date('d/m/Y');
        $ano        = $e['ano_letivo_nome'] ?? date('Y');
        $turma      = $e['turma_nome']      ?? '—';

        // Financial summary
        $valorBruto   = (float)($contrato['valor_bruto']   ?? 0);
        $valorDesc    = (float)($contrato['valor_desconto'] ?? 0);
        $valorLiq     = (float)($contrato['valor_liquido']  ?? 0);
        $diaVenc      = $contrato['dia_vencimento'] ?? '—';
        $numParcelas  = $contrato['num_parcelas']   ?? 0;

        // Build itens table rows
        $itensHtml = '';
        foreach ($itens as $item) {
            $cat  = $catLabel[$item['categoria']] ?? $item['categoria'];
            $parc = $item['num_parcelas'] > 1
                ? $item['num_parcelas'] . 'x de ' . $brl($item['valor_liquido'] / $item['num_parcelas'])
                : 'À vista';
            $itensHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td><td style="text-align:center">%s</td></tr>',
                $esc($cat),
                $esc($item['descricao']),
                $brl($item['valor_total']),
                $brl($item['valor_liquido']),
                $esc($parc)
            );
        }
        $tabelaFinanceira = '';
        if ($itensHtml) {
            $descontoRow = $valorDesc > 0
                ? "<tr><td colspan='3' style='text-align:right;font-style:italic;color:#666;'>Desconto total</td><td style='text-align:right;color:#16a34a;'>- {$brl($valorDesc)}</td><td></td></tr>"
                : '';
            $tabelaFinanceira = <<<FIN
<div class="section-title">3. Condições Financeiras</div>
<table class="dados-fin">
  <thead>
    <tr>
      <th>Categoria</th>
      <th>Descrição</th>
      <th style="text-align:right">Valor Bruto</th>
      <th style="text-align:right">Valor Líquido</th>
      <th style="text-align:center">Forma</th>
    </tr>
  </thead>
  <tbody>
    {$itensHtml}
    {$descontoRow}
    <tr class="total-row">
      <td colspan="3" style="text-align:right">TOTAL</td>
      <td style="text-align:right">{$brl($valorLiq)}</td>
      <td></td>
    </tr>
  </tbody>
</table>
<p style="font-size:9pt;margin-top:6px;">
  Vencimento: todo dia <strong>{$esc($diaVenc)}</strong> de cada mês &nbsp;|&nbsp;
  Total de {$esc($numParcelas)} parcela(s).
</p>
FIN;
        }

        $logoHtml = $logoUrl
            ? "<img src=\"{$esc($logoUrl)}\" style=\"max-height:70px;display:block;margin:0 auto 8px;\">"
            : '';
        $cnpjHtml = $cnpj ? "<br>CNPJ: {$esc($cnpj)}" : '';
        $endHtml  = $endEscola ? "<br>{$esc($endEscola)}" : '';

        $obsClausula = $this->renderClausulasObservacoes($e['observacoes'] ?? '');

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 30px 40px; }

  /* Header */
  .header { border-bottom: 2px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 20px; }
  .header-inner { display: table; width: 100%; }
  .header-logo { display: table-cell; width: 80px; vertical-align: middle; }
  .header-info { display: table-cell; vertical-align: middle; padding-left: 12px; }
  .escola-nome { font-size: 14pt; font-weight: bold; color: #1d4ed8; }
  .escola-sub  { font-size: 8pt; color: #555; margin-top: 2px; }
  .doc-title   { display: table-cell; vertical-align: middle; text-align: right; }
  .doc-title h1 { font-size: 13pt; color: #1d4ed8; }
  .doc-title p  { font-size: 8pt; color: #777; margin-top: 2px; }

  /* Section titles */
  .section-title {
    font-size: 10pt; font-weight: bold; color: #fff;
    background: #1d4ed8; padding: 4px 8px;
    margin: 16px 0 8px; border-radius: 2px;
  }

  /* Dados table */
  table.dados { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  table.dados td { padding: 5px 8px; border: 1px solid #d1d5db; font-size: 9.5pt; }
  table.dados td.label { background: #f3f4f6; font-weight: bold; width: 38%; color: #374151; }
  table.dados td.val2 { width: 28%; }
  table.dados tr:nth-child(even) td:not(.label) { background: #fafafa; }

  /* Financial table */
  table.dados-fin { width: 100%; border-collapse: collapse; font-size: 9pt; }
  table.dados-fin th { background: #1e3a5f; color: #fff; padding: 5px 8px; font-weight: bold; font-size: 8.5pt; }
  table.dados-fin td { padding: 5px 8px; border: 1px solid #d1d5db; }
  table.dados-fin tbody tr:nth-child(even) { background: #f9fafb; }
  table.dados-fin .total-row td { background: #eff6ff; font-weight: bold; border-top: 2px solid #1d4ed8; }

  /* Clauses */
  .clausula { margin: 10px 0; text-align: justify; font-size: 9.5pt; line-height: 1.5; }
  .clausula strong { display: block; color: #1d4ed8; margin-bottom: 3px; }

  /* Signature area */
  .assinatura-wrap { margin-top: 36px; border-top: 1px solid #9ca3af; padding-top: 12px; }
  .assinatura-cols { display: table; width: 100%; }
  .assinatura-col  { display: table-cell; width: 50%; text-align: center; padding: 0 20px; }
  .sig-line { border-top: 1px solid #333; margin: 32px auto 6px; width: 80%; }
  .sig-label { font-size: 8.5pt; color: #555; }

  /* Footer */
  .rodape { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 7.5pt; color: #9ca3af; text-align: center; }
</style>
</head>
<body>

<!-- CABEÇALHO -->
<div class="header">
  <div class="header-inner">
    <div class="header-logo">{$logoHtml}</div>
    <div class="header-info">
      <div class="escola-nome">{$esc($nomeEscola)}</div>
      <div class="escola-sub">{$cnpjHtml}{$endHtml}</div>
    </div>
    <div class="doc-title">
      <h1>Contrato de {$esc($tipo)}</h1>
      <p>Ano Letivo {$esc($ano)} &nbsp;|&nbsp; Emitido em {$hoje}</p>
    </div>
  </div>
</div>

<!-- 1. DADOS DO ALUNO -->
<div class="section-title">1. Dados do Aluno</div>
<table class="dados">
  <tr>
    <td class="label">Nome completo</td><td>{$esc($e['aluno_nome'])}</td>
    <td class="label">Turma</td><td class="val2">{$esc($turma)}</td>
  </tr>
  <tr>
    <td class="label">CPF</td><td>{$esc($e['aluno_cpf'] ?? '—')}</td>
    <td class="label">Data de nascimento</td><td>{$fmtDt($e['aluno_data_nasc'] ?? '')}</td>
  </tr>
  <tr>
    <td class="label">Gênero</td><td>{$esc($e['aluno_genero'] ?? '—')}</td>
    <td class="label">E-mail</td><td>{$esc($e['aluno_email'] ?? '—')}</td>
  </tr>
</table>

<!-- 2. DADOS DO RESPONSÁVEL -->
<div class="section-title">2. Responsável Legal</div>
<table class="dados">
  <tr>
    <td class="label">Nome completo</td><td>{$esc($e['resp_nome'])}</td>
    <td class="label">Parentesco</td><td class="val2">{$esc($e['resp_parentesco'] ?? '—')}</td>
  </tr>
  <tr>
    <td class="label">CPF</td><td>{$esc($e['resp_cpf'] ?? '—')}</td>
    <td class="label">Telefone</td><td>{$esc($e['resp_telefone'] ?? '—')}</td>
  </tr>
  <tr>
    <td class="label">E-mail</td><td colspan="3">{$esc($e['resp_email'] ?? '—')}</td>
  </tr>
  <tr>
    <td class="label">Endereço</td><td colspan="3">{$esc($e['resp_endereco'] ?? '—')}</td>
  </tr>
</table>

<!-- 3. CONDIÇÕES FINANCEIRAS -->
{$tabelaFinanceira}

<!-- 4. CLÁUSULAS -->
<div class="section-title">{$esc($tabelaFinanceira ? '4' : '3')}. Cláusulas Contratuais</div>

<div class="clausula">
  <strong>Objeto</strong>
  O presente contrato tem por objeto a {$esc($tipo)} do(a) aluno(a) identificado(a) acima
  para o ano letivo {$esc($ano)}, na turma {$esc($turma)}.
</div>

<div class="clausula">
  <strong>Obrigações da Instituição</strong>
  A instituição compromete-se a oferecer ensino de qualidade, infraestrutura adequada,
  comunicação transparente com a família e cumprir o calendário letivo aprovado.
</div>

<div class="clausula">
  <strong>Obrigações do Responsável</strong>
  O responsável compromete-se a: (a) manter os dados cadastrais atualizados;
  (b) honrar as obrigações financeiras nas datas estabelecidas;
  (c) acompanhar o desenvolvimento do(a) aluno(a) e comunicar à escola qualquer
  alteração relevante; (d) respeitar e fazer respeitar as normas internas da instituição.
</div>

<div class="clausula">
  <strong>Vigência e Rescisão</strong>
  Este contrato é válido para o ano letivo {$esc($ano)}.
  Rescisão antecipada por parte do responsável deverá ser comunicada por escrito com
  antecedência mínima de 30 dias, sem prejuízo das obrigações financeiras já vencidas.
</div>

{$obsClausula}

<!-- ASSINATURAS -->
<div class="assinatura-wrap">
  <div class="assinatura-cols">
    <div class="assinatura-col">
      <div class="sig-line"></div>
      <div class="sig-label">{$esc($nomeEscola)}<br>Representante Legal</div>
    </div>
    <div class="assinatura-col">
      <div class="sig-line"></div>
      <div class="sig-label">{$esc($e['resp_nome'])}<br>Responsável pelo(a) aluno(a) — {$esc($e['resp_parentesco'] ?? '—')}</div>
    </div>
  </div>
  <p style="text-align:center;font-size:8pt;margin-top:12px;color:#6b7280;">
    {$esc($nomeEscola)}, {$hoje}
  </p>
</div>

<!-- RODAPÉ -->
<div class="rodape">
  Documento gerado eletronicamente pelo sistema EducaTudo em {$hoje}.
  A validade jurídica é confirmada pelo hash de verificação gravado no momento da assinatura.
</div>

</body>
</html>
HTML;
    }

    private function renderClausulasObservacoes(string $obs): string
    {
        if (trim($obs) === '') return '';
        return '<div class="clausula"><strong>5. Observações</strong>' . nl2br(htmlspecialchars($obs, ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    // ── Registra assinatura do responsável ───────────────────────────────────

    public function registrarAssinatura(array $enrollment, string $ip, string $nomeAssinante): void
    {
        $hash = hash('sha256', ($enrollment['contrato_hash'] ?? '') . $ip . date('c'));
        $this->model->update($enrollment['id'], [
            'status'          => 'confirmada',
            'assinado_em'     => date('Y-m-d H:i:s'),
            'assinante_ip'    => $ip,
            'assinante_nome'  => $nomeAssinante,
            'contrato_hash'   => $hash,
        ]);
        $this->model->transition($enrollment['id'], 'confirmada', null, 'assinatura_responsavel');
    }

    // ── Link WhatsApp ─────────────────────────────────────────────────────────

    public function buildWhatsAppLink(array $enrollment, string $baseUrl): string
    {
        $token = $enrollment['contrato_token'] ?? '';
        $url   = rtrim($baseUrl, '/') . '/matricula/contrato/' . $token;
        $msg   = "Olá, {$enrollment['resp_nome']}! 👋\n\n"
               . "A {$enrollment['tipo']} de *{$enrollment['aluno_nome']}* está pronta para assinatura.\n\n"
               . "Clique no link para revisar e assinar o contrato:\n{$url}\n\n"
               . "_EducaTudo_";
        $phone = preg_replace('/\D/', '', $enrollment['resp_telefone'] ?? '');
        if ($phone && !str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
    }

    // ── Dados da escola ───────────────────────────────────────────────────────

    public function getEscola(): array
    {
        $rows = $this->db->fetchAll("SELECT config_key, config_value FROM config_layout") ?: [];
        $cfg  = [];
        foreach ($rows as $r) {
            $cfg[$r['config_key']] = $r['config_value'];
        }
        // Normalize to expected keys
        return [
            'nome'      => $cfg['nome_escola']  ?? $cfg['system_title']    ?? 'Escola',
            'cnpj'      => $cfg['cnpj']          ?? '',
            'endereco'  => $cfg['endereco']      ?? '',
            'cidade'    => $cfg['cidade']        ?? '',
            'uf'        => $cfg['uf']            ?? '',
            'telefone'  => $cfg['telefone']      ?? '',
            'email'     => $cfg['email_escola']  ?? '',
            'logo_url'  => $cfg['logo_url']      ?? '',
        ];
    }

    public function getAnosLetivos(): array
    {
        return $this->db->fetchAll("SELECT id, ano, ativo FROM ano_letivo ORDER BY ano DESC") ?: [];
    }

    public function getTurmas(?int $anoLetivoId = null): array
    {
        $sql    = "SELECT t.id, t.nome, t.serie
                   FROM turmas t WHERE t.ativo = 1";
        $params = [];
        if ($anoLetivoId) {
            $sql   .= " AND t.ano_letivo_id = ?";
            $params[] = $anoLetivoId;
        }
        $sql .= " ORDER BY t.serie, t.nome";
        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function getModel(): Enrollment
    {
        return $this->model;
    }
}
