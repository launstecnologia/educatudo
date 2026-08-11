#!/usr/bin/env node
/**
 * MCP Server — Jornadas dos Alunos (EducaTudo)
 *
 * Env: EDUCATUDO_BASE_URL, EDUCATUDO_COOKIE, EDUCATUDO_CSRF
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
const ENDPOINT = "/admin/consulta-jornadas-aluno/mcp/ferramenta";

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
    description: "Busca alunos por nome/RA; opcionalmente filtra por turma.",
    inputSchema: {
      type: "object",
      properties: {
        termo: { type: "string" },
        turma: { type: "string", description: "Ex.: 2 Ano B, 2ªB" },
      },
      required: ["termo"],
      additionalProperties: false,
    },
  },
  {
    name: "listar_materias_jornadas",
    description: "Lista matérias usadas em jornadas.",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_jornadas_aluno",
    description:
      "Lista jornadas do aluno com status, % de conclusão, período, matéria, acertos/erros.",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        aluno_nome: { type: "string" },
        turma_nome: { type: "string" },
        materia_id: { type: "integer" },
        materia_nome: { type: "string" },
        bimestre: { type: "integer" },
        status_aluno: {
          type: "string",
          description: "concluida | em andamento | nao iniciada",
        },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
        limite: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "detalhar_jornada_aluno",
    description:
      "Detalhe item a item: enunciado, alternativa marcada × correta, acerto/erro. Use somente_erros=true para só os erros.",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        jornada_id: { type: "integer" },
        somente_erros: { type: "boolean" },
      },
      required: ["aluno_id", "jornada_id"],
      additionalProperties: false,
    },
  },
  {
    name: "resumo_jornadas_aluno",
    description:
      "Consolidado: totais concluídas/em andamento e médias por matéria e bimestre.",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        aluno_nome: { type: "string" },
        turma_nome: { type: "string" },
        materia_nome: { type: "string" },
        bimestre: { type: "integer" },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
        limite: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
];

const server = new Server(
  { name: "educatudo-jornadas-aluno", version: "1.0.0" },
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
      case "listar_materias_jornadas":
        result = await postTool({ tool: "listar_materias_jornadas" });
        break;
      case "listar_jornadas_aluno":
        result = await postTool({ tool: "listar_jornadas_aluno", ...args });
        break;
      case "detalhar_jornada_aluno":
        result = await postTool({
          tool: "detalhar_jornada_aluno",
          aluno_id: args.aluno_id,
          jornada_id: args.jornada_id,
          somente_erros: args.somente_erros ? "1" : "",
        });
        break;
      case "resumo_jornadas_aluno":
        result = await postTool({ tool: "resumo_jornadas_aluno", ...args });
        break;
      default:
        throw new Error(`Tool desconhecida: ${name}`);
    }
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  } catch (err) {
    return {
      isError: true,
      content: [{ type: "text", text: String(err?.message || err) }],
    };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
