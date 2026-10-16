import fs from 'fs';
import path from 'path';
import { Achado, Gravidade, lerAchados } from '../registro';

export type TesteExecutado = {
  titulo: string;
  arquivo: string;
  projeto: string;
  status: string;
  duracaoMs: number;
  erro?: string;
  anexos: Array<{ name: string; path: string }>;
};

export type Execucao = {
  status: string;
  duracaoMs: number;
  testes: TesteExecutado[];
};

const ORDEM: Gravidade[] = ['Crítico', 'Alto', 'Médio', 'Baixo'];

function esc(valor: unknown): string {
  return String(valor ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function ms(valor: number | null | undefined): string {
  if (valor == null) return '—';
  if (valor < 1000) return `${valor} ms`;
  return `${(valor / 1000).toFixed(1)} s`;
}

export function gerarRelatorio(execucao: Execucao): string {
  const achados = lerAchados();
  const dirRelatorio = path.join(__dirname, '..', '..', 'relatorios');
  fs.mkdirSync(dirRelatorio, { recursive: true });
  const html = montar(execucao, achados, dirRelatorio);
  const destino = path.join(dirRelatorio, 'educatudo-qa.html');
  fs.writeFileSync(destino, html);
  return destino;
}

function montar(execucao: Execucao, achados: Achado[], dirRelatorio: string): string {
  const testes = execucao.testes.filter((t) => t.projeto !== 'setup' || t.status !== 'skipped');
  const aprovados = testes.filter((t) => t.status === 'passed').length;
  const reprovados = testes.filter((t) => t.status === 'failed' || t.status === 'timedOut').length;
  const ignorados = testes.filter((t) => t.status === 'skipped').length;
  const ux = achados.filter((a) => a.tipo === 'ux' && a.cliques != null);
  const obstaculos = achados.filter((a) => a.tipo === 'ux' && a.cliques == null);
  const a11y = achados.filter((a) => a.tipo === 'acessibilidade' && a.regra);
  const erros = achados.filter((a) => a.tipo === 'erro');
  const porGravidade = (g: Gravidade) => achados.filter((a) => a.gravidade === g && a.defeitoConfirmado);

  return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Relatório QA EducaTudo</title>
  <style>
    body { font-family: "Segoe UI", sans-serif; margin: 0; background: #f4f6f8; color: #1f2933; }
    header { background: #1e293b; color: #fff; padding: 28px 32px; }
    header p { margin: 6px 0 0; color: #cbd5e1; }
    main { padding: 24px 32px 64px; max-width: 1200px; }
    .cards { display: grid; grid-cols: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin: 20px 0; }
    .card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
    .card b { display: block; font-size: 28px; }
    section { background: #fff; border-radius: 12px; padding: 18px 20px; margin: 16px 0; }
    h2 { margin: 0 0 12px; font-size: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    th { color: #64748b; font-weight: 600; }
    .tag { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 12px; }
    .passed { background: #dcfce7; color: #166534; }
    .failed, .timedOut { background: #fee2e2; color: #991b1b; }
    .skipped { background: #e2e8f0; color: #334155; }
    .Crítico { background: #fee2e2; color: #991b1b; }
    .Alto { background: #ffedd5; color: #9a3412; }
    .Médio { background: #fef9c3; color: #854d0e; }
    .Baixo { background: #e0f2fe; color: #075985; }
    .aviso { background: #fff7ed; border: 1px solid #fed7aa; padding: 12px 14px; border-radius: 10px; }
    a { color: #1d4ed8; }
  </style>
</head>
<body>
<header>
  <h1>Relatório QA — EducaTudo</h1>
  <p>Funcional, usabilidade e acessibilidade. Sem chamadas de IA e sem gravar dados (modo somente leitura).</p>
  <p>Duração da execução: ${ms(execucao.duracaoMs)} · status do runner: ${esc(execucao.status)}</p>
</header>
<main>
  <div class="cards">
    <div class="card"><b>${testes.length}</b>testes</div>
    <div class="card"><b>${aprovados}</b>aprovados</div>
    <div class="card"><b>${reprovados}</b>reprovados</div>
    <div class="card"><b>${ignorados}</b>ignorados</div>
    <div class="card"><b>${ms(execucao.duracaoMs)}</b>duração</div>
  </div>

  <section>
    <h2>Priorização</h2>
    <p>A gravidade só aparece quando há evidência (status HTTP, regra do axe, mensagem ausente ou requisição observada). Alerta de clique ou de tempo não vira defeito.</p>
    <div class="cards">
      ${ORDEM.map((g) => `<div class="card"><b>${porGravidade(g).length}</b><span class="tag ${g}">${g}</span> com evidência</div>`).join('')}
    </div>
  </section>

  <section>
    <h2>Relatório funcional</h2>
    <table>
      <thead><tr><th>Teste</th><th>Arquivo</th><th>Resultado</th><th>Duração</th><th>Erro</th><th>Evidência</th></tr></thead>
      <tbody>
        ${testes.map((t) => `<tr>
          <td>${esc(t.titulo)}</td>
          <td>${esc(t.arquivo)}</td>
          <td><span class="tag ${esc(t.status)}">${esc(t.status)}</span></td>
          <td>${ms(t.duracaoMs)}</td>
          <td>${esc(t.erro || '')}</td>
          <td>${links(t.anexos, dirRelatorio)}</td>
        </tr>`).join('')}
      </tbody>
    </table>
  </section>

  <section>
    <h2>Relatório de UX</h2>
    <p class="aviso">Os números medem o caminho executado pelo teste. Ultrapassar o limite gera alerta de complexidade. Não é uma nota de facilidade de uso.</p>
    <table>
      <thead><tr><th>Fluxo</th><th>Módulo</th><th>Cliques</th><th>Etapas</th><th>Campos</th><th>Páginas</th><th>Modais</th><th>Tempo da aplicação</th><th>Tempo da automação</th><th>Alerta</th></tr></thead>
      <tbody>
        ${ux.map((a) => `<tr>
          <td>${esc(a.fluxo)}<br><small>${esc(a.detalhe)}</small></td>
          <td>${esc(a.modulo)}</td>
          <td>${a.cliques ?? '—'} / ${a.limiteCliques ?? '—'}</td>
          <td>${a.etapas ?? '—'}</td>
          <td>${a.campos ?? '—'}</td>
          <td>${a.navegacoes ?? '—'}</td>
          <td>${a.modais ?? '—'}</td>
          <td>${ms(a.tempoAplicacaoMs)}</td>
          <td>${ms(a.tempoParedeMs)}</td>
          <td>${a.recomendacao ? esc(a.recomendacao) : 'dentro do limite'}</td>
        </tr>`).join('') || '<tr><td colspan="10">Nenhum fluxo medido.</td></tr>'}
      </tbody>
    </table>
    <h3>Obstáculos observados</h3>
    <table>
      <thead><tr><th>Fluxo</th><th>Achado</th><th>Gravidade</th><th>Evidência</th><th>Confirmação</th></tr></thead>
      <tbody>
        ${obstaculos.map((a) => linhaAchado(a)).join('') || '<tr><td colspan="5">Nenhum obstáculo heurístico.</td></tr>'}
      </tbody>
    </table>
  </section>

  <section>
    <h2>Relatório de acessibilidade</h2>
    <p class="aviso">A auditoria usa axe-core nas páginas principais. Ela não substitui avaliação manual com teclado, leitor de tela e pessoas com deficiência.</p>
    <table>
      <thead><tr><th>Página</th><th>Regra</th><th>Gravidade</th><th>Elemento</th><th>Descrição</th></tr></thead>
      <tbody>
        ${a11y.map((a) => `<tr>
          <td>${esc(a.pagina)}</td>
          <td>${esc(a.regra)}<br><small>${esc(a.titulo)}</small></td>
          <td>${a.gravidade ? `<span class="tag ${a.gravidade}">${a.gravidade}</span>` : '—'}</td>
          <td><code>${esc(a.elemento)}</code></td>
          <td>${esc(a.detalhe)}<br><small>${esc(a.evidencia)}</small></td>
        </tr>`).join('') || '<tr><td colspan="5">Nenhuma violação do axe nas páginas auditadas.</td></tr>'}
      </tbody>
    </table>
  </section>

  <section>
    <h2>Erros de navegador e HTTP</h2>
    <p>Respostas 400 e 401 de testes negativos de login não entram aqui. Gravações interceptadas pelo modo somente leitura também não são falha da aplicação.</p>
    <table>
      <thead><tr><th>Teste</th><th>Achado</th><th>Gravidade</th><th>Evidência</th></tr></thead>
      <tbody>
        ${erros.map((a) => `<tr>
          <td>${esc(a.fluxo)}</td>
          <td>${esc(a.titulo)}<br><small>${esc(a.detalhe)}</small></td>
          <td>${a.gravidade ? `<span class="tag ${a.gravidade}">${a.gravidade}</span>` : '—'}</td>
          <td>${esc(a.evidencia)}</td>
        </tr>`).join('') || '<tr><td colspan="4">Nenhum erro de console ou HTTP fora do filtro.</td></tr>'}
      </tbody>
    </table>
  </section>

  <section>
    <h2>Limitações</h2>
    <ul>
      <li>O teste não usa a conta como uma pessoa usaria o ano inteiro: ele não grava aluno, matrícula, nota nem boletim.</li>
      <li>Contagem de cliques vale para o caminho do script, não para todos os atalhos possíveis do menu.</li>
      <li>Tempo da aplicação vem do Navigation Timing (até DOMContentLoaded). Tempo de parede inclui a automação.</li>
      <li>Obstáculos de nome acessível e de rolagem são heurísticos.</li>
      <li>axe-core não avalia ordem de foco, compreensão do texto nem contraste de imagens.</li>
      <li>Nenhuma API de IA foi chamada. Botões de importar grade por imagem e de transcrição não são acionados.</li>
    </ul>
  </section>
</main>
</body>
</html>`;
}

function linhaAchado(a: Achado): string {
  return `<tr>
    <td>${esc(a.fluxo)}<br><small>${esc(a.modulo)}</small></td>
    <td>${esc(a.titulo)}<br><small>${esc(a.detalhe)}</small></td>
    <td>${a.gravidade ? `<span class="tag ${a.gravidade}">${a.gravidade}</span>` : '—'}</td>
    <td><code>${esc(a.elemento || a.evidencia || a.pagina)}</code></td>
    <td>${a.defeitoConfirmado ? 'com evidência de regra ou comportamento' : 'heurística — confirmar na tela'}</td>
  </tr>`;
}

function links(anexos: Array<{ name: string; path: string }>, dirRelatorio: string): string {
  return anexos
    .filter((a) => a.path && /screenshot|video|trace/i.test(a.name))
    .map((a) => {
      const rel = path.relative(dirRelatorio, a.path);
      return `<a href="${esc(rel)}">${esc(a.name)}</a>`;
    })
    .join('<br>');
}
