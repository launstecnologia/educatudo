import AxeBuilder from '@axe-core/playwright';
import { Page } from '@playwright/test';
import { Gravidade, registrar } from './registro';

const GRAVIDADE: Record<string, Gravidade> = {
  critical: 'Crítico',
  serious: 'Alto',
  moderate: 'Médio',
  minor: 'Baixo',
};

export async function auditarAcessibilidade(page: Page, modulo: string, fluxo: string): Promise<number> {
  let resultados;
  try {
    resultados = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .exclude('#sidebar')
      .analyze();
  } catch (erro) {
    registrar({
      tipo: 'acessibilidade',
      modulo,
      fluxo,
      titulo: 'Axe não concluiu',
      detalhe: erro instanceof Error ? erro.message : String(erro),
      pagina: page.url().split('?')[0],
      defeitoConfirmado: false,
      recomendacao: 'Falha da auditoria automatizada, não da página.',
    });
    return 0;
  }

  for (const violacao of resultados.violations) {
    const alvo = violacao.nodes[0];
    const gravidade = GRAVIDADE[violacao.impact || 'minor'] ?? 'Baixo';
    registrar({
      tipo: 'acessibilidade',
      modulo,
      fluxo,
      titulo: violacao.help,
      regra: violacao.id,
      detalhe: violacao.description,
      elemento: alvo?.target?.join(' ') || alvo?.html?.slice(0, 180),
      evidencia: alvo?.failureSummary || violacao.helpUrl,
      pagina: page.url().split('?')[0],
      gravidade,
      defeitoConfirmado: true,
      recomendacao: 'Achado do axe-core. A auditoria automatizada não substitui avaliação manual de acessibilidade.',
    });
  }
  return resultados.violations.length;
}
