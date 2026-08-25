import { primeiroMatch } from './html.js';
import { cpfUnico } from './alunoForm.js';

function pad(n, tam) {
  return String(n).padStart(tam, '0');
}

function primeiroSelectId(html, name) {
  return primeiroMatch(html, new RegExp('name="' + name + '"[\\s\\S]*?<option value="(\\d+)"'));
}

export function idsDaTelaMatricula(html) {
  return {
    anoLetivoId: primeiroSelectId(html, 'ano_letivo_id'),
    turmaId: primeiroSelectId(html, 'turma_id'),
    planoId: primeiroSelectId(html, 'cobrancas\\[0\\]\\[plan_id\\]')
      || primeiroMatch(html, /name="cobrancas\[0\]\[plan_id\]"[\s\S]*?<option value="(\d+)"/),
  };
}

export function payloadNovaMatricula(indice, csrf, ids) {
  const n = pad(indice, 5);
  const cpfAluno = cpfUnico(indice);
  const cpfResp = cpfUnico(indice + 80000);

  return {
    _token: csrf,
    tipo: 'nova',
    origem: 'interno',
    aluno_id: '',
    ano_letivo_id: ids.anoLetivoId || '',
    turma_id: ids.turmaId || '',
    aluno_nome: `Aluno Matricula Carga ${n}`,
    aluno_cpf: cpfAluno,
    aluno_rg: `${pad(indice, 8)}-${indice % 10}`,
    aluno_data_nasc: `201${indice % 10}-0${(indice % 9) + 1}-${pad((indice % 28) + 1, 2)}`,
    aluno_telefone: `(31) 98888-${pad(indice % 10000, 4)}`,
    aluno_email: `matricula${n}@carga.local`,
    aluno_escola_anterior: 'Escola Municipal de Teste',
    aluno_end_cep: '30130-000',
    aluno_endereco: `Rua de Teste Matrícula ${n}`,
    aluno_end_numero: String(100 + (indice % 900)),
    aluno_end_complemento: `Apto ${(indice % 200) + 1}`,
    aluno_end_bairro: 'Centro',
    aluno_end_cidade: 'Belo Horizonte',
    aluno_end_uf: 'MG',
    'responsaveis[0][nome]': `Maria Matricula ${n}`,
    'responsaveis[0][cpf]': cpfResp,
    'responsaveis[0][rg]': `${pad(indice + 1, 8)}-1`,
    'responsaveis[0][data_nascimento]': '1985-05-12',
    'responsaveis[0][telefone]': `(31) 97777-${pad(indice % 10000, 4)}`,
    'responsaveis[0][email]': `resp.matricula${n}@carga.local`,
    'responsaveis[0][estado_civil]': 'Casado(a)',
    'responsaveis[0][tipo_vinculo]': 'Mãe',
    'responsaveis[0][profissao]': 'Analista',
    'responsaveis[0][empresa]': 'Empresa Carga',
    'responsaveis[0][end_cep]': '30130-000',
    'responsaveis[0][endereco]': `Rua de Teste Matrícula ${n}`,
    'responsaveis[0][end_numero]': String(100 + (indice % 900)),
    'responsaveis[0][end_bairro]': 'Centro',
    'responsaveis[0][end_complemento]': '',
    'responsaveis[0][end_cidade]': 'Belo Horizonte',
    'responsaveis[0][end_uf]': 'MG',
    'responsaveis[0][is_pedagogico]': '1',
    'responsaveis[0][is_financeiro]': '1',
    'responsaveis[0][percentual]': '100',
    resp_nome: `Maria Matricula ${n}`,
    resp_cpf: cpfResp,
    resp_email: `resp.matricula${n}@carga.local`,
    resp_telefone: `(31) 97777-${pad(indice % 10000, 4)}`,
    resp_parentesco: 'Mãe',
    resp_endereco: `Rua de Teste Matrícula ${n}, ${100 + (indice % 900)} — Centro, Belo Horizonte/MG`,
    finance_plan_id: ids.planoId || '',
    'cobrancas[0][tipo]': 'mensalidade',
    'cobrancas[0][plan_id]': ids.planoId || '',
    observacoes: `Processo gerado no teste de carga K6 (matricula${n}).`,
  };
}

export function idDoProcesso(resp) {
  const loc = String((resp.headers && (resp.headers.Location || resp.headers.location)) || resp.url || '');
  const m = loc.match(/\/admin\/(?:enrollment|matricula)\/(\d+)/);
  if (m) {
    return Number(m[1]);
  }
  const corpo = String(resp.body || '');
  const m2 = corpo.match(/Matrícula\s*#\s*(\d+)/i) || corpo.match(/\/admin\/enrollment\/(\d+)/);
  return m2 ? Number(m2[1]) : 0;
}
