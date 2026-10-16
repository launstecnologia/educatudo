import { Page, Route } from '@playwright/test';
import { registrar } from './registro';

const PADROES_IA = [
  /openai\.com/i,
  /api\.anthropic\.com/i,
  /elevenlabs/i,
  /processar-imagem-ia/i,
  /salvar-importacao-ia/i,
  /analise-tudinha/i,
  /assistente\/mensagem/i,
  /transcrever-audio/i,
  /auto-preencher/i,
  /exercicio-ia/i,
  /generativelanguage\.googleapis\.com/i,
];

export type GravacaoBloqueada = { metodo: string; url: string };

export function gravacaoPermitida(): boolean {
  return process.env.QA_PERMITIR_GRAVACAO === '1';
}

export async function instalarProtecoes(page: Page, destino: GravacaoBloqueada[]): Promise<void> {
  page.on('dialog', (dialog) => {
    const mensagem = dialog.message();
    if (!/erro de conexão/i.test(mensagem)) {
      registrar({
        tipo: 'ux',
        modulo: 'Geral',
        fluxo: 'Diálogo nativo',
        titulo: 'A página abriu um alerta do navegador',
        detalhe: mensagem,
        evidencia: mensagem,
        pagina: page.url(),
        defeitoConfirmado: false,
        recomendacao: 'Alerta nativo interrompe o fluxo. Conferir se a mensagem é o feedback esperado da ação.',
      });
    }
    void dialog.accept();
  });

  await page.route('**/*', async (route: Route) => {
    const req = route.request();
    const url = req.url();
    const metodo = req.method();

    if (PADROES_IA.some((padrao) => padrao.test(url))) {
      registrar({
        tipo: 'seguranca',
        modulo: 'IA',
        fluxo: 'Bloqueio de IA',
        titulo: 'Chamada de IA interceptada',
        detalhe: 'A suíte abortou a requisição para não consumir token nem chamar API de IA.',
        evidencia: `${metodo} ${url.split('?')[0]}`,
        pagina: url.split('?')[0],
        gravidade: 'Baixo',
        defeitoConfirmado: false,
      });
      await route.abort('blockedbyclient');
      return;
    }

    if (/enviar-recuperacao|recuperar-senha\/reset/i.test(url) && metodo !== 'GET') {
      registrar({
        tipo: 'seguranca',
        modulo: 'Autenticação',
        fluxo: 'Recuperar senha',
        titulo: 'Envio de recuperação bloqueado',
        detalhe: 'O teste não envia e-mail nem redefine senha.',
        evidencia: `${metodo} ${url.split('?')[0]}`,
        pagina: url.split('?')[0],
        defeitoConfirmado: false,
      });
      await route.abort('blockedbyclient');
      return;
    }

    const leitura = metodo === 'GET' || metodo === 'HEAD' || metodo === 'OPTIONS';
    if (req.resourceType() === 'document' || leitura) {
      await route.fallback();
      return;
    }

    const login = /\/login(\?|$)/.test(new URL(url).pathname);
    if (login || gravacaoPermitida()) {
      await route.fallback();
      return;
    }

    destino.push({ metodo, url: url.split('?')[0] });
    await route.fulfill({
      status: 409,
      contentType: 'application/json',
      body: JSON.stringify({ success: false, error: 'gravacao bloqueada pelo QA' }),
    });
  });
}
