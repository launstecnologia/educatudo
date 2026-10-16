import { Locator, Page } from '@playwright/test';
import { registrar } from '../registro';

export class Fluxo {
  cliques = 0;
  etapas: string[] = [];
  navegacoes = 0;
  modais = 0;
  campos = 0;
  tempoAplicacaoMs: number | null = null;
  tempoParedeMs = 0;
  private inicio = Date.now();

  constructor(
    readonly nome: string,
    readonly modulo: string,
    readonly limiteCliques: number,
    readonly limiteMs: number,
  ) {}

  etapa(nome: string): void {
    this.etapas.push(nome);
  }

  async ir(page: Page, rota: string, nome: string): Promise<void> {
    this.etapa(nome);
    const t0 = Date.now();
    await page.goto(rota, { waitUntil: 'domcontentloaded' });
    this.navegacoes += 1;
    const nav = await tempoDaAplicacao(page);
    const parede = Date.now() - t0;
    this.tempoAplicacaoMs = (this.tempoAplicacaoMs ?? 0) + (nav ?? parede);
    this.tempoParedeMs += parede;
  }

  async clicar(alvo: Locator, nome: string): Promise<void> {
    this.etapa(nome);
    this.cliques += 1;
    await alvo.click();
  }

  async contarCampos(page: Page): Promise<void> {
    this.campos = await page.locator('input:visible, select:visible, textarea:visible').count();
  }

  async marcarModal(page: Page): Promise<void> {
    const aberto = await page.locator('aside:visible, [role="dialog"]:visible, .fixed.inset-0:visible').count();
    if (aberto > 0) this.modais += 1;
  }

  publicar(pagina?: string): void {
    const excedeuCliques = this.cliques > this.limiteCliques;
    const tempo = this.tempoAplicacaoMs ?? 0;
    const excedeuTempo = tempo > this.limiteMs;
    registrar({
      tipo: 'ux',
      modulo: this.modulo,
      fluxo: this.nome,
      titulo: this.nome,
      detalhe: this.etapas.join(' → ') || 'Fluxo sem etapas registradas.',
      pagina,
      cliques: this.cliques,
      etapas: this.etapas.length,
      campos: this.campos,
      navegacoes: this.navegacoes,
      modais: this.modais,
      tempoAplicacaoMs: this.tempoAplicacaoMs,
      tempoParedeMs: this.tempoParedeMs || Date.now() - this.inicio,
      limiteCliques: this.limiteCliques,
      limiteMs: this.limiteMs,
      gravidade: excedeuCliques || excedeuTempo ? 'Baixo' : undefined,
      defeitoConfirmado: false,
      recomendacao: recomendacao(excedeuCliques, excedeuTempo, this),
    });
  }
}

function recomendacao(cliques: boolean, tempo: boolean, fluxo: Fluxo): string | undefined {
  const partes: string[] = [];
  if (cliques) {
    partes.push(
      `Este caminho usou ${fluxo.cliques} cliques. O limite configurado para o fluxo é ${fluxo.limiteCliques}. Isso é um alerta de complexidade, não uma nota de facilidade.`,
    );
  }
  if (tempo) {
    partes.push(
      `O tempo de carregamento medido na aplicação foi ${fluxo.tempoAplicacaoMs} ms. O limite configurado é ${fluxo.limiteMs} ms. O tempo de parede da automação fica separado no relatório.`,
    );
  }
  return partes.join(' ') || undefined;
}

export async function tempoDaAplicacao(page: Page): Promise<number | null> {
  return page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined;
    if (!nav) return null;
    const fim = nav.domContentLoadedEventEnd > 0 ? nav.domContentLoadedEventEnd : nav.responseEnd;
    return Math.round(fim - nav.startTime);
  });
}
