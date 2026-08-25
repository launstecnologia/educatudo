import { primeiroMatch } from './html.js';

function pad(n, tam) {
  return String(n).padStart(tam, '0');
}

export function cpfUnico(indice) {
  const base = pad(100000000 + (indice % 100000000), 9);
  const dig = (nums, pesoIni) => {
    let soma = 0;
    for (let i = 0; i < nums.length; i++) {
      soma += Number(nums[i]) * (pesoIni - i);
    }
    const r = (soma * 10) % 11;
    return r === 10 ? '0' : String(r);
  };
  const d1 = dig(base, 10);
  const d2 = dig(base + d1, 11);
  const d = base + d1 + d2;
  return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
}

const CORES = ['Branca', 'Preta', 'Parda', 'Amarela', 'Indígena', 'Não declarada'];
const SANGUES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const SEXOS = ['F', 'M', 'N'];

/**
 * Mesmos name= da tela admin/students/create.php e partials.
 */
export function payloadCadastroCompleto(indice, nickname, senha, csrf, htmlTela) {
  const n = pad(indice, 5);
  const unidadeId = htmlTela ? primeiroMatch(htmlTela, /name="unidade_id"[\s\S]*?<option value="(\d+)"/) : '';

  return {
    _token: csrf,
    nome: `Aluno Carga ${nickname}`,
    codigo_aluno: nickname,
    ra: nickname,
    sexo: SEXOS[indice % SEXOS.length],
    unidade_id: unidadeId,
    cpf: cpfUnico(indice),
    rg: `${pad(indice, 8)}-${indice % 10}`,
    data_nasc: `201${indice % 10}-0${(indice % 9) + 1}-${pad((indice % 28) + 1, 2)}`,
    logradouro: `Rua de Teste Carga ${n}`,
    numero: String(100 + (indice % 900)),
    complemento: `Apto ${(indice % 200) + 1}`,
    bairro: 'Centro',
    cidade: 'Belo Horizonte',
    uf: 'MG',
    cep: '30130-000',
    telefone: `(31) 3333-${pad(indice % 10000, 4)}`,
    celular: `(31) 98888-${pad(indice % 10000, 4)}`,
    whatsapp: `(31) 98888-${pad(indice % 10000, 4)}`,
    email: `${nickname}@carga.local`,
    email_secundario: `${nickname}.resp@carga.local`,
    nome_mae: `Maria Carga ${n}`,
    nome_pai: `José Carga ${n}`,
    codigo_inep: `3${pad(indice, 11)}`,
    nome_social: '',
    nacionalidade: 'Brasileira',
    cor_raca: CORES[indice % CORES.length],
    naturalidade: 'Belo Horizonte',
    uf_nascimento: 'MG',
    orgao_emissor: 'SSP',
    uf_rg: 'MG',
    nis: `1${pad(indice, 10)}`,
    zona: indice % 2 === 0 ? 'urbana' : 'rural',
    certidao_nascimento: `32${pad(indice, 30)}`,
    certidao_livro: pad((indice % 99) + 1, 3),
    certidao_folha: pad((indice % 200) + 1, 3),
    certidao_termo: pad((indice % 9000) + 1, 4),
    pais: 'Brasil',
    passaporte: '',
    rne: '',
    nickname,
    senha,
    ativo: '1',
    pagante: '1',
    tipo_sanguineo: SANGUES[indice % SANGUES.length],
    plano_saude: 'Unimed Carga',
    plano_saude_numero: `CART${n}`,
    hospital_referencia: 'Hospital das Clínicas',
    alergias: 'Nenhuma alergia conhecida (lote de carga).',
    medicamentos_uso: 'Nenhum medicamento contínuo.',
    condicoes_cronicas: 'Sem condições crônicas informadas.',
    deficiencias_obs: '',
    contato_emergencia_nome: `Maria Carga ${n}`,
    contato_emergencia_telefone: `(31) 97777-${pad(indice % 10000, 4)}`,
    contato_emergencia_parentesco: 'Mãe',
    restricoes_alimentares: 'Sem restrições.',
    alimentacao_obs: 'Come normalmente na merenda.',
    usa_transporte_escolar: '1',
    transporte_tipo: 'escolar',
    transporte_rota: `Rota ${1 + (indice % 12)}`,
    transporte_ponto: `Ponto ${n} — Praça da Estação`,
    transporte_responsavel: `Motorista Carga ${1 + (indice % 20)}`,
    transporte_telefone: `(31) 96666-${pad(indice % 10000, 4)}`,
    observacoes_gerais: `Aluno gerado no teste de carga K6 (${nickname}).`,
  };
}
