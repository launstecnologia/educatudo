import fs from 'fs';
import path from 'path';

export type Gravidade = 'Crítico' | 'Alto' | 'Médio' | 'Baixo';

export type Achado = {
  tipo: 'funcional' | 'ux' | 'acessibilidade' | 'erro' | 'responsivo' | 'teclado' | 'seguranca';
  modulo: string;
  fluxo: string;
  titulo: string;
  detalhe: string;
  gravidade?: Gravidade;
  evidencia?: string;
  pagina?: string;
  regra?: string;
  elemento?: string;
  cliques?: number;
  etapas?: number;
  campos?: number;
  navegacoes?: number;
  modais?: number;
  tempoAplicacaoMs?: number | null;
  tempoParedeMs?: number;
  limiteCliques?: number;
  limiteMs?: number;
  recomendacao?: string;
  defeitoConfirmado: boolean;
  teste?: string;
};

const dir = path.join(__dirname, '..', 'resultados');
const arquivo = path.join(dir, 'achados.jsonl');

export function limparRegistro(): void {
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(arquivo, '');
}

export function caminhoAchados(): string {
  return arquivo;
}

function ocultar(valor: string | undefined): string | undefined {
  if (!valor) return valor;
  return valor
    .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, '[email]')
    .replace(/(senha|password|token|authorization|cookie)(["'\s:=]+)[^\s"',&]+/gi, '$1$2[oculto]')
    .slice(0, 700);
}

export function registrar(achado: Achado): void {
  fs.mkdirSync(dir, { recursive: true });
  const limpo: Achado = {
    ...achado,
    detalhe: ocultar(achado.detalhe) ?? '',
    evidencia: ocultar(achado.evidencia),
    elemento: ocultar(achado.elemento)?.slice(0, 300),
    pagina: achado.pagina?.split('?')[0],
  };
  fs.appendFileSync(arquivo, JSON.stringify(limpo) + '\n');
}

export function lerAchados(): Achado[] {
  if (!fs.existsSync(arquivo)) return [];
  return fs
    .readFileSync(arquivo, 'utf8')
    .split('\n')
    .map((linha) => linha.trim())
    .filter(Boolean)
    .map((linha) => JSON.parse(linha) as Achado);
}
