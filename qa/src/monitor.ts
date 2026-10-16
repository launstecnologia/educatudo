import { Page, TestInfo } from '@playwright/test';
import { registrar } from './registro';
import { GravacaoBloqueada, instalarProtecoes } from './protecoes';

type FalhaHttp = { status: number; url: string; metodo: string };

export class Monitor {
  readonly errosJs: string[] = [];
  readonly http: FalhaHttp[] = [];
  readonly gravacoes: GravacaoBloqueada[] = [];
  private ignorar: Array<{ status: number; trecho: string }> = [];

  constructor(private readonly teste: string) {}

  ignorarStatus(status: number, trecho: string): void {
    this.ignorar.push({ status, trecho });
  }

  anexar(page: Page): void {
    page.on('pageerror', (erro) => {
      this.errosJs.push(erro.message);
    });
    page.on('console', (msg) => {
      if (msg.type() !== 'error') return;
      const texto = msg.text();
      if (/favicon|ERR_BLOCKED_BY_CLIENT|Failed to load resource/i.test(texto)) return;
      this.errosJs.push(texto);
    });
    page.on('response', (resposta) => {
      const status = resposta.status();
      const url = resposta.url();
      if (status < 400) return;
      if (/favicon|manifest|service-worker|chrome-extension/i.test(url)) return;
      if (this.ignorar.some((item) => item.status === status && url.includes(item.trecho))) return;
      this.http.push({ status, url: url.split('?')[0], metodo: resposta.request().method() });
    });
    page.on('requestfailed', (req) => {
      const falha = req.failure()?.errorText ?? '';
      if (/ERR_BLOCKED_BY_CLIENT|ERR_ABORTED/i.test(falha)) return;
      this.errosJs.push(`${req.method()} ${req.url().split('?')[0]} ${falha}`);
    });
  }

  publicar(pagina?: string): void {
    const vistos = new Set<string>();
    for (const erro of this.errosJs) {
      const chave = `js:${erro}`;
      if (vistos.has(chave)) continue;
      vistos.add(chave);
      registrar({
        tipo: 'erro',
        modulo: 'Navegador',
        fluxo: this.teste,
        titulo: 'Erro JavaScript',
        detalhe: erro,
        evidencia: erro,
        pagina,
        gravidade: 'Alto',
        defeitoConfirmado: true,
        teste: this.teste,
      });
    }
    for (const item of this.http) {
      const chave = `${item.status}:${item.url}`;
      if (vistos.has(chave)) continue;
      vistos.add(chave);
      const critico = item.status >= 500;
      registrar({
        tipo: 'erro',
        modulo: 'HTTP',
        fluxo: this.teste,
        titulo: `HTTP ${item.status}`,
        detalhe: `${item.metodo} ${item.url}`,
        evidencia: `${item.status} ${item.metodo} ${item.url}`,
        pagina: item.url,
        gravidade: critico ? 'Crítico' : 'Médio',
        defeitoConfirmado: true,
        teste: this.teste,
        recomendacao: critico
          ? 'Resposta 5xx impede a operação. Investigar o log do PHP desta rota.'
          : 'Resposta 4xx observada fora de um teste negativo. Confirmar se a rota existe e se a sessão é aceita.',
      });
    }
  }
}

export async function prepararPagina(page: Page, teste: TestInfo): Promise<Monitor> {
  const monitor = new Monitor(teste.title);
  monitor.anexar(page);
  await instalarProtecoes(page, monitor.gravacoes);
  return monitor;
}
