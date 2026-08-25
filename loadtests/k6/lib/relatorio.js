function num(v, casas) {
  if (v === undefined || v === null || Number.isNaN(Number(v))) {
    return '—';
  }
  return Number(v).toFixed(casas === undefined ? 2 : casas);
}

function ms(v) {
  if (v === undefined || v === null || Number.isNaN(Number(v))) {
    return '—';
  }
  const n = Number(v);
  if (n >= 1000) {
    return (n / 1000).toFixed(2) + ' s';
  }
  return n.toFixed(0) + ' ms';
}

function pct(v) {
  if (v === undefined || v === null || Number.isNaN(Number(v))) {
    return '—';
  }
  return (Number(v) * 100).toFixed(1) + '%';
}

function metrica(data, nome) {
  const m = data.metrics && data.metrics[nome];
  return (m && m.values) || {};
}

function veredito(p95, falhas, ok, alvo) {
  if (ok < alvo * 0.85) {
    return { classe: 'ruim', texto: 'Não aguentou — muitas matrículas falharam' };
  }
  if (falhas > 0.15 || p95 > 5000) {
    return { classe: 'alerta', texto: 'Saturando — respondeu, mas lento ou com erro' };
  }
  if (p95 > 2500) {
    return { classe: 'alerta', texto: 'Aguentou com folga curta — p(95) acima de 2,5 s' };
  }
  return { classe: 'ok', texto: 'Aguentou — tempo e taxa de erro aceitáveis' };
}

function checksHtml(data) {
  const grupos = [];
  function andar(g) {
    if (!g) {
      return;
    }
    if (g.checks) {
      for (let i = 0; i < g.checks.length; i++) {
        grupos.push(g.checks[i]);
      }
    }
    if (g.groups) {
      for (let j = 0; j < g.groups.length; j++) {
        andar(g.groups[j]);
      }
    }
  }
  andar(data.root_group);
  if (grupos.length === 0) {
    return '<tr><td colspan="3">Nenhum check</td></tr>';
  }
  return grupos.map((c) => {
    const total = (c.passes || 0) + (c.fails || 0);
    const ok = total > 0 ? ((c.passes / total) * 100).toFixed(0) : '0';
    const cls = (c.fails || 0) > 0 ? 'ruim' : 'ok';
    return `<tr><td>${esc(c.name)}</td><td class="${cls}">${c.passes}/${total}</td><td class="${cls}">${ok}%</td></tr>`;
  }).join('');
}

function esc(s) {
  return String(s || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function montarHtml(resumo) {
  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Relatório K6 — ${esc(resumo.cenario)}</title>
  <style>
    :root { --ok:#15803d; --alerta:#b45309; --ruim:#b91c1c; --bg:#f8fafc; --card:#fff; }
    body { font-family: ui-sans-serif, system-ui, sans-serif; background: var(--bg); color:#0f172a; margin:0; padding:32px; }
    h1 { font-size:1.6rem; margin:0 0 4px; }
    .sub { color:#64748b; margin-bottom:24px; }
    .veredito { padding:14px 18px; border-radius:12px; font-weight:700; margin-bottom:24px; }
    .veredito.ok { background:#dcfce7; color:var(--ok); }
    .veredito.alerta { background:#ffedd5; color:var(--alerta); }
    .veredito.ruim { background:#fee2e2; color:var(--ruim); }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:24px; }
    .card { background:var(--card); border:1px solid #e2e8f0; border-radius:12px; padding:16px; }
    .card .k { font-size:.75rem; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
    .card .v { font-size:1.4rem; font-weight:700; margin-top:4px; }
    table { width:100%; border-collapse:collapse; background:var(--card); border-radius:12px; overflow:hidden; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:.9rem; }
    th { background:#f1f5f9; color:#475569; }
    td.ok { color:var(--ok); font-weight:600; }
    td.ruim { color:var(--ruim); font-weight:600; }
    h2 { font-size:1.1rem; margin:28px 0 12px; }
  </style>
</head>
<body>
  <h1>Relatório de carga — ${esc(resumo.cenario)}</h1>
  <p class="sub">${esc(resumo.alvo)} · ${esc(resumo.quando)} · ${esc(resumo.vus)} VUs · ${esc(resumo.total)} iterações · ${esc(resumo.duracao)}</p>
  <div class="veredito ${resumo.veredito.classe}">${esc(resumo.veredito.texto)}</div>
  <div class="grid">
    <div class="card"><div class="k">Matrículas ok</div><div class="v">${esc(resumo.ok)}</div></div>
    <div class="card"><div class="k">Falhas</div><div class="v">${esc(resumo.erro)}</div></div>
    <div class="card"><div class="k">Anexos ok</div><div class="v">${esc(resumo.anexos)}</div></div>
    <div class="card"><div class="k">Requests</div><div class="v">${esc(resumo.reqs)}</div></div>
    <div class="card"><div class="k">Req/s</div><div class="v">${esc(resumo.rps)}</div></div>
    <div class="card"><div class="k">Erro HTTP</div><div class="v">${esc(resumo.httpFalhas)}</div></div>
  </div>
  <h2>Tempo de resposta</h2>
  <div class="grid">
    <div class="card"><div class="k">Médio</div><div class="v">${esc(resumo.avg)}</div></div>
    <div class="card"><div class="k">Mediana</div><div class="v">${esc(resumo.med)}</div></div>
    <div class="card"><div class="k">p(95)</div><div class="v">${esc(resumo.p95)}</div></div>
    <div class="card"><div class="k">p(99)</div><div class="v">${esc(resumo.p99)}</div></div>
    <div class="card"><div class="k">Máximo</div><div class="v">${esc(resumo.max)}</div></div>
  </div>
  <h2>Checks</h2>
  <table>
    <thead><tr><th>Check</th><th>Passou</th><th>Taxa</th></tr></thead>
    <tbody>${resumo.checks}</tbody>
  </table>
  <p class="sub" style="margin-top:24px">p(95) é o número que importa: 95% das requests ficaram abaixo desse tempo. Acima de 3 s a HML já está sentindo.</p>
</body>
</html>`;
}

export function handleSummary(data) {
  const dur = data.state && data.state.testRunDurationMs ? data.state.testRunDurationMs : 0;
  const httpd = metrica(data, 'http_req_duration');
  const failed = metrica(data, 'http_req_failed');
  const reqs = metrica(data, 'http_reqs');
  const ok = metrica(data, 'matriculas_ok').count
    || metrica(data, 'cadastros_ok').count
    || metrica(data, 'acessos_ok').count
    || 0;
  const erro = metrica(data, 'matriculas_erro').count
    || metrica(data, 'cadastros_erro').count
    || metrica(data, 'acessos_erro').count
    || 0;
  const anexos = metrica(data, 'anexos_ok').count || 0;
  const alvo = Number(__ENV.TOTAL_ALUNOS || 0) || (ok + erro) || 1;
  const p95 = Number(httpd['p(95)'] || 0);
  const agora = new Date();
  const stamp = agora.toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const cenario = __ENV.CENARIO || 'k6';

  const resumo = {
    cenario,
    alvo: __ENV.BASE_URL || '',
    quando: agora.toLocaleString('pt-BR'),
    vus: String(__ENV.VUS || ''),
    total: String(alvo),
    duracao: (dur / 1000).toFixed(1) + ' s',
    ok: String(ok),
    erro: String(erro),
    anexos: String(anexos),
    reqs: String(reqs.count || 0),
    rps: num(reqs.rate, 1),
    httpFalhas: pct(failed.rate),
    avg: ms(httpd.avg),
    med: ms(httpd.med),
    p95: ms(httpd['p(95)']),
    p99: ms(httpd['p(99)'] || httpd['p(95)']),
    max: ms(httpd.max),
    veredito: veredito(p95, Number(failed.rate || 0), Number(ok), alvo),
    checks: checksHtml(data),
  };

  const html = montarHtml(resumo);
  const json = JSON.stringify({ resumo, k6: data }, null, 2);
  const pasta = 'relatorios';

  const texto = [
    '',
    '=== Relatório ' + cenario + ' ===',
    resumo.veredito.texto,
    'Matrículas/cadastros ok: ' + resumo.ok + '  |  falhas: ' + resumo.erro + '  |  anexos: ' + resumo.anexos,
    'HTTP erro: ' + resumo.httpFalhas + '  |  p(95): ' + resumo.p95 + '  |  médio: ' + resumo.avg,
    'Arquivo: ' + pasta + '/' + cenario + '-' + stamp + '.html',
    '',
  ].join('\n');

  const out = {};
  out[pasta + '/' + cenario + '-' + stamp + '.html'] = html;
  out[pasta + '/' + cenario + '-ultimo.html'] = html;
  out[pasta + '/' + cenario + '-' + stamp + '.json'] = json;
  out[pasta + '/' + cenario + '-ultimo.json'] = json;
  out.stdout = texto;
  return out;
}
