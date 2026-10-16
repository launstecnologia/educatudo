import { Page } from '@playwright/test';
import { registrar } from '../registro';

type Obstaculo = {
  titulo: string;
  detalhe: string;
  elemento?: string;
  gravidade: 'Médio' | 'Baixo';
};

export async function detectarObstaculos(page: Page, modulo: string, fluxo: string): Promise<Obstaculo[]> {
  const achados = await page.evaluate(() => {
    const saida: Array<{ titulo: string; detalhe: string; elemento?: string; gravidade: 'Médio' | 'Baixo' }> = [];
    const visivel = (el: Element) => {
      const estilo = window.getComputedStyle(el);
      const ret = el.getBoundingClientRect();
      return estilo.visibility !== 'hidden' && estilo.display !== 'none' && ret.width > 0 && ret.height > 0;
    };
    const nome = (el: Element) => {
      const html = el as HTMLElement;
      return (
        html.getAttribute('aria-label') ||
        html.getAttribute('title') ||
        (html.innerText || '').replace(/\s+/g, ' ').trim()
      );
    };

    const largura = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    if (largura > 8) {
      saida.push({
        titulo: 'Rolagem horizontal',
        detalhe: `A página é ${largura}px mais larga que a área visível.`,
        gravidade: 'Médio',
      });
    }

    const botoes = Array.from(document.querySelectorAll('button, a, [role="button"]')).filter(visivel);
    let semNome = 0;
    for (const el of botoes) {
      if (nome(el)) continue;
      if ((el as HTMLElement).querySelector('svg, i')) {
        semNome += 1;
        if (semNome <= 8) {
          saida.push({
            titulo: 'Controle sem nome acessível',
            detalhe: 'Botão ou link visível só com ícone, sem texto, aria-label ou title.',
            elemento: el.outerHTML.slice(0, 180),
            gravidade: 'Médio',
          });
        }
      }
    }

    const campos = Array.from(document.querySelectorAll('input, select, textarea')).filter((el) => {
      const input = el as HTMLInputElement;
      if (['hidden', 'submit', 'button', 'reset'].includes(input.type)) return false;
      if (input.name === 'site_url' || input.name === '_token') return false;
      return visivel(el);
    });
    let semLabel = 0;
    for (const el of campos) {
      const input = el as HTMLInputElement;
      const id = input.id;
      const porFor = id ? document.querySelector(`label[for="${CSS.escape(id)}"]`) : null;
      const embrulho = el.closest('label');
      if (porFor || embrulho || input.getAttribute('aria-label') || input.getAttribute('aria-labelledby')) continue;
      semLabel += 1;
      if (semLabel <= 8) {
        saida.push({
          titulo: 'Campo sem identificação acessível',
          detalhe: `Campo name="${input.name || ''}" sem label associado, aria-label ou aria-labelledby.`,
          elemento: el.outerHTML.slice(0, 180),
          gravidade: 'Médio',
        });
      }
    }

    const vistaLargura = window.innerWidth;
    const vistaAltura = window.innerHeight;
    const cobertos = Array.from(document.querySelectorAll('button, a, input, select')).filter(visivel).slice(0, 40);
    for (const el of cobertos) {
      const ret = el.getBoundingClientRect();
      if (ret.bottom < 0 || ret.top > vistaAltura || ret.right < 0 || ret.left > vistaLargura) {
        const estilo = window.getComputedStyle(el);
        if (estilo.position === 'fixed' || estilo.position === 'sticky') {
          saida.push({
            titulo: 'Controle fixo fora da área visível',
            detalhe: 'Elemento com posição fixa ou sticky ficou fora da viewport.',
            elemento: (el as HTMLElement).innerText?.slice(0, 80) || el.tagName,
            gravidade: 'Médio',
          });
          break;
        }
      }
    }

    return saida;
  });

  for (const item of achados) {
    registrar({
      tipo: 'ux',
      modulo,
      fluxo,
      titulo: item.titulo,
      detalhe: item.detalhe,
      elemento: item.elemento,
      pagina: page.url().split('?')[0],
      gravidade: item.gravidade,
      evidencia: item.elemento || item.detalhe,
      defeitoConfirmado: false,
      recomendacao: 'Obstáculo detectado por heurística. Confirmar na tela antes de tratar como defeito.',
    });
  }
  return achados;
}
