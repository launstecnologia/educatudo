#!/usr/bin/env node
/**
 * MCP Server — Provas dos Alunos (EducaTudo)
 *
 * Consulta somente leitura: aluno → provas (semanal, bimestral…),
 * acertos/erros, nota, data, matéria, tipo de avaliação.
 *
 * Env:
 *   EDUCATUDO_BASE_URL  ex.: http://localhost:8000
 *   EDUCATUDO_COOKIE    cookie de sessão admin (PHPSESSID=...)
 *   EDUCATUDO_CSRF      token CSRF da sessão (_token)
 *
 * Cursor mcp.json exemplo:
 * {
 *   "mcpServers": {
 *     "educatudo-provas-aluno": {
 *       "command": "node",
 *       "args": ["/caminho/src/mcp/provas-aluno/src/index.js"],
 *       "env": {
 *         "EDUCATUDO_BASE_URL": "http://localhost:8000",
 *         "EDUCATUDO_COOKIE": "PHPSESSID=...",
 *         "EDUCATUDO_CSRF": "..."
 *       }
 *     }
 *   }
 * }
 */

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

const BASE = (process.env.EDUCATUDO_BASE_URL || "").replace(/\/$/, "");
const COOKIE = process.env.EDUCATUDO_COOKIE || "";
const CSRF = process.env.EDUCATUDO_CSRF || "";
const ENDPOINT = "/admin/consulta-provas-aluno/mcp/ferramenta";

function assertConfig() {
  if (!BASE) throw new Error("Defina EDUCATUDO_BASE_URL");
  if (!COOKIE) throw new Error("Defina EDUCATUDO_COOKIE (sessão admin)");
  if (!CSRF) throw new Error("Defina EDUCATUDO_CSRF (_token da sessão)");
}

async function postTool(fields = {}) {
  assertConfig();
  const body = new URLSearchParams();
  body.set("_token", CSRF);
  for (const [k, v] of Object.entries(fields)) {
    if (v === undefined || v === null || v === "") continue;
    body.set(k, String(v));
  }
  const res = await fetch(`${BASE}${ENDPOINT}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
      Cookie: COOKIE,
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    body,
  });
  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`Resposta não-JSON (${res.status}): ${text.slice(0, 300)}`);
  }
  if (!res.ok || json.success === false) {
    throw new Error(json.error || `HTTP ${res.status}`);
  }
  return json;
}

const TOOLS = [
  {
    name: "buscar_alunos",
    description:
      "Busca alunos ativos por nome parcial, RA ou código. Use antes de listar provas quando só tiver o nome (ex.: Lucas).",
    inputSchema: {
      type: "object",
      properties: {
        termo: {
          type: "string",
          description: "Nome parcial, RA ou código do aluno (mín. 2 caracteres)",
        },
        turma: {
          type: "string",
          description: "Filtro de turma (ex.: 2 Ano B, 2ªB)",
        },
      },
      required: ["termo"],
      additionalProperties: false,
    },
  },
  {
    name: "listar_materias",
    description: "Lista matérias da escola (para filtrar provas por matéria).",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_tipos_avaliacao",
    description:
      "Lista tipos de avaliação (Prova Semanal, Bimestral, ENAC, Trabalho…). Use o nome ou id ao filtrar.",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_provas_aluno",
    description:
      "Lista provas do aluno com detalhe: título, matéria, tipo (semanal/bimestral…), evento (data/bimestre), realização (dia, nota, acertos, erros, %, status). Filtre por matéria e/ou tipo e/ou período.",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: {
          type: "integer",
          description: "ID do aluno (preferencial; use buscar_alunos se só tiver o nome)",
        },
        aluno_nome: {
          type: "string",
          description: "Nome do aluno. Se ambíguo, a API devolve candidatos.",
        },
        turma_nome: {
          type: "string",
          description: "Turma (ex.: 2 Ano B, 2ªB) — ajuda a desambiguar o aluno",
        },
        materia_id: { type: "integer" },
        materia_nome: {
          type: "string",
          description: "Ex.: Matemática, Português",
        },
        tipo_avaliacao_id: { type: "integer" },
        tipo_avaliacao_nome: {
          type: "string",
          description: "Ex.: semanal, Prova Bimestral, ENAC",
        },
        bimestre: {
          type: "integer",
          description: "1 a 4",
        },
        data_inicio: {
          type: "string",
          description: "YYYY-MM-DD ou dd/mm/aaaa",
        },
        data_fim: {
          type: "string",
          description: "YYYY-MM-DD ou dd/mm/aaaa",
        },
        status: {
          type: "string",
          description: "finalizado (padrão), todos, em_andamento, cancelada",
        },
        limite: {
          type: "integer",
          description: "Máximo de registros (1–200, padrão 50)",
        },
      },
      additionalProperties: false,
    },
  },
  {
    name: "detalhar_prova_aluno",
    description:
      "Detalhe item a item: enunciado, alternativa marcada, alternativa correta, acerto/erro. Use somente_erros=true para listar só as que errou.",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        prova_id: { type: "integer" },
        somente_erros: {
          type: "boolean",
          description: "Se true, retorna apenas questões erradas / sem resposta correta",
        },
      },
      required: ["aluno_id", "prova_id"],
      additionalProperties: false,
    },
  },
  {
    name: "resumo_provas_aluno",
    description:
      "Resumo agregado: total de acertos/erros e quantidade de provas por tipo, matéria e bimestre (mesmos filtros de listar_provas_aluno).",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        aluno_nome: { type: "string" },
        materia_id: { type: "integer" },
        materia_nome: { type: "string" },
        tipo_avaliacao_id: { type: "integer" },
        tipo_avaliacao_nome: { type: "string" },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
        status: { type: "string" },
        limite: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
];

const server = new Server(
  { name: "educatudo-provas-aluno", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};

  try {
    let result;
    switch (name) {
      case "buscar_alunos":
        result = await postTool({ tool: "buscar_alunos", termo: args.termo, turma: args.turma });
        break;
      case "listar_materias":
        result = await postTool({ tool: "listar_materias" });
        break;
      case "listar_tipos_avaliacao":
        result = await postTool({ tool: "listar_tipos_avaliacao" });
        break;
      case "listar_provas_aluno":
        result = await postTool({
          tool: "listar_provas_aluno",
          aluno_id: args.aluno_id,
          aluno_nome: args.aluno_nome,
          turma_nome: args.turma_nome,
          materia_id: args.materia_id,
          materia_nome: args.materia_nome,
          tipo_avaliacao_id: args.tipo_avaliacao_id,
          tipo_avaliacao_nome: args.tipo_avaliacao_nome,
          bimestre: args.bimestre,
          data_inicio: args.data_inicio,
          data_fim: args.data_fim,
          status: args.status,
          limite: args.limite,
        });
        break;
      case "detalhar_prova_aluno":
        result = await postTool({
          tool: "detalhar_prova_aluno",
          aluno_id: args.aluno_id,
          prova_id: args.prova_id,
          somente_erros: args.somente_erros ? "1" : "",
        });
        break;
      case "resumo_provas_aluno":
        result = await postTool({
          tool: "resumo_provas_aluno",
          aluno_id: args.aluno_id,
          aluno_nome: args.aluno_nome,
          turma_nome: args.turma_nome,
          materia_id: args.materia_id,
          materia_nome: args.materia_nome,
          tipo_avaliacao_id: args.tipo_avaliacao_id,
          tipo_avaliacao_nome: args.tipo_avaliacao_nome,
          bimestre: args.bimestre,
          data_inicio: args.data_inicio,
          data_fim: args.data_fim,
          status: args.status,
          limite: args.limite,
        });
        break;
      default:
        throw new Error(`Tool desconhecida: ${name}`);
    }

    return {
      content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
    };
  } catch (err) {
    return {
      isError: true,
      content: [{ type: "text", text: String(err?.message || err) }],
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
