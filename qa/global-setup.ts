import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';
import { limparRegistro } from './src/registro';

dotenv.config({ path: path.join(__dirname, '.env') });

function ambienteSeguro(url: string): void {
  let host = '';
  try {
    host = new URL(url).hostname;
  } catch {
    throw new Error(`QA_BASE_URL inválida: ${url}`);
  }
  if (/(^|\.)educatudo\.com$/i.test(host)) {
    throw new Error('A suíte recusou um host de produção (educatudo.com).');
  }
  const local = host === 'localhost' || host === '127.0.0.1' || host.endsWith('.localhost');
  if (!local && process.env.QA_CONFIRMAR_AMBIENTE !== 'homologacao') {
    throw new Error('Fora de localhost, defina QA_CONFIRMAR_AMBIENTE=homologacao. Produção continua bloqueada.');
  }
}

export default async function globalSetup(): Promise<void> {
  const base = process.env.QA_BASE_URL ?? 'http://colag.localhost';
  ambienteSeguro(base);
  const raiz = __dirname;
  fs.mkdirSync(path.join(raiz, '.auth'), { recursive: true });
  fs.mkdirSync(path.join(raiz, 'resultados'), { recursive: true });
  fs.mkdirSync(path.join(raiz, 'relatorios'), { recursive: true });
  limparRegistro();
}
