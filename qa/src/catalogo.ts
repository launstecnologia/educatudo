export type ItemCatalogo = {
  modulo: string;
  fluxo: string;
  titulo: string | RegExp;
  rota: string;
  limiteCliques: number;
  limiteMs: number;
  axe?: boolean;
  seletor?: string;
};

/** Rotas reais do admin. Títulos saem das views, não de suposição. */
export const PAGINAS: ItemCatalogo[] = [
  { modulo: 'Alunos', fluxo: 'Listar alunos', titulo: 'Lista de Alunos', rota: '/admin/students', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Alunos', fluxo: 'Abrir cadastro de aluno', titulo: 'Cadastrar aluno', rota: '/admin/students/create', limiteCliques: 3, limiteMs: 8000, axe: true },
  { modulo: 'Matrícula', fluxo: 'Listar matrículas', titulo: 'Matrículas', rota: '/admin/matricula', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Matrícula', fluxo: 'Abrir nova matrícula', titulo: 'Nova Matrícula', rota: '/admin/matricula/create', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Vida escolar', fluxo: 'Abrir vida escolar', titulo: 'Vida Escolar', rota: '/admin/vida-escolar', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Ano letivo', fluxo: 'Listar anos letivos', titulo: 'Anos Letivos', rota: '/admin/ano-letivo', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Grade horária', fluxo: 'Abrir grade horária', titulo: 'Grade Horária de Aulas', rota: '/admin/grade-horaria', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Calendário', fluxo: 'Abrir calendário letivo', titulo: /Calendário Letivo/, rota: '/admin/calendario-letivo', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Avaliação', fluxo: 'Abrir regras de aprovação', titulo: 'Regras de Aprovação', rota: '/admin/regras-academicas', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Avaliação', fluxo: 'Abrir nova regra de aprovação', titulo: 'Nova regra de aprovação', rota: '/admin/regras-academicas/nova', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Avaliação', fluxo: 'Abrir tipos de nota', titulo: 'Tipos de Nota', rota: '/admin/provas/tipos-avaliacao', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Avaliação', fluxo: 'Abrir novo tipo de nota', titulo: 'Novo Tipo de Nota', rota: '/admin/provas/tipos-avaliacao/criar', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Avaliação', fluxo: 'Abrir quadro de notas', titulo: 'Quadro de Notas', rota: '/admin/quadros-notas', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Avaliação', fluxo: 'Abrir novo quadro de notas', titulo: 'Novo quadro de notas', rota: '/admin/quadros-notas/novo', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Boletim', fluxo: 'Abrir modelos de boletim', titulo: 'Modelo de Boletim', rota: '/admin/boletins', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Boletim', fluxo: 'Abrir novo modelo de boletim', titulo: 'Novo modelo de boletim', rota: '/admin/boletins/novo', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Boletim', fluxo: 'Abrir configurar notas', titulo: 'Evento de Notas', rota: '/admin/boletim-configuracao/assistente', limiteCliques: 2, limiteMs: 10000, axe: true, seletor: '#evento-notas-titulo' },
  { modulo: 'Boletim', fluxo: 'Abrir boletins gerados', titulo: 'Versão de Notas', rota: '/admin/boletim-configuracao/gerados', limiteCliques: 2, limiteMs: 8000 },
  { modulo: 'Lançamento', fluxo: 'Abrir lançamento de notas', titulo: 'Avaliações e Notas', rota: '/admin/provas', limiteCliques: 2, limiteMs: 10000, axe: true },
  { modulo: 'Lançamento', fluxo: 'Abrir novo lançamento', titulo: /Criar Evento|Prova/, rota: '/admin/provas/blocos/criar', limiteCliques: 3, limiteMs: 10000 },
  { modulo: 'Fechamento', fluxo: 'Abrir fechamento', titulo: 'Fechamento', rota: '/admin/fechamento', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Fechamento', fluxo: 'Abrir implantar acadêmico', titulo: 'Implantar acadêmico', rota: '/admin/implantar-academico', limiteCliques: 2, limiteMs: 8000 },
  { modulo: 'Conselho', fluxo: 'Abrir conselho de classe', titulo: 'Conselho de Classe', rota: '/admin/conselhos', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Conselho', fluxo: 'Abrir novo conselho', titulo: 'Iniciar Conselho de Classe', rota: '/admin/conselhos/novo', limiteCliques: 3, limiteMs: 8000 },
  { modulo: 'Diário', fluxo: 'Abrir diário de classe', titulo: 'Acompanhamento do Diário', rota: '/admin/diario', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Ocorrências', fluxo: 'Abrir ocorrências', titulo: 'Ocorrências', rota: '/admin/ocorrencias', limiteCliques: 2, limiteMs: 8000, axe: true },
  { modulo: 'Frequência', fluxo: 'Abrir frequência', titulo: 'Frequência', rota: '/admin/presenca', limiteCliques: 2, limiteMs: 8000, axe: true },
];

export const ROTAS_RESPONSIVAS = [
  '/admin/students',
  '/admin/students/create',
  '/admin/ano-letivo',
  '/admin/boletins',
  '/admin/provas',
  '/admin/diario',
  '/admin/calendario-letivo',
];

export const VIEWPORTS = [
  { nome: 'Desktop', width: 1920, height: 1080 },
  { nome: 'Notebook', width: 1366, height: 768 },
  { nome: 'Tablet', width: 768, height: 1024 },
  { nome: 'Mobile', width: 390, height: 844 },
];
