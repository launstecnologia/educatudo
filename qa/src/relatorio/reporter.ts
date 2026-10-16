import fs from 'fs';
import path from 'path';
import type { FullResult, Reporter, TestCase, TestResult } from '@playwright/test/reporter';
import { gerarRelatorio, TesteExecutado } from './gerar';

class RelatorioEducaTudo implements Reporter {
  private testes: TesteExecutado[] = [];

  onTestEnd(teste: TestCase, resultado: TestResult): void {
    this.testes.push({
      titulo: teste.title,
      arquivo: path.basename(teste.location.file),
      projeto: teste.parent.project()?.name ?? '',
      status: resultado.status,
      duracaoMs: resultado.duration,
      erro: resultado.error?.message?.split('\n')[0],
      anexos: resultado.attachments
        .filter((anexo) => anexo.path)
        .map((anexo) => ({ name: anexo.name, path: anexo.path as string })),
    });
  }

  onEnd(resultado: FullResult): void {
    const dir = path.join(__dirname, '..', '..', 'resultados');
    fs.mkdirSync(dir, { recursive: true });
    const execucao = {
      status: resultado.status,
      duracaoMs: resultado.duration,
      testes: this.testes,
    };
    fs.writeFileSync(path.join(dir, 'execucao.json'), JSON.stringify(execucao, null, 2));
    const html = gerarRelatorio(execucao);
    console.log(`\nRelatório QA: ${html}`);
  }
}

export default RelatorioEducaTudo;
