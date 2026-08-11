#!/usr/bin/env node
/**
 * MCP Server — Provas / saúde na visão do Professor (EducaTudo)
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
const ENDPOINT = "/admin/consulta-provas-professor/mcp/ferramenta";

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
    name: "buscar_professores",
    description: "Busca professores por nome, e-mail ou código.",
    inputSchema: {
      type: "object",
      properties: { termo: { type: "string" } },
      required: ["termo"],
      additionalProperties: false,
    },
  },
  {
    name: "listar_turmas_professor",
    description: "Turmas vinculadas ao professor (grade / provas).",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        professor_nome: { type: "string" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "listar_provas_professor",
    description: "Provas do professor com acertos/erros agregados dos alunos.",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        professor_nome: { type: "string" },
        turma_nome: { type: "string" },
        materia_nome: { type: "string" },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
        limite: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "resumo_provas_professor",
    description: "Totais de acertos/erros das provas do professor por matéria e turma.",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        professor_nome: { type: "string" },
        turma_nome: { type: "string" },
        materia_nome: { type: "string" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "detalhar_prova_professor",
    description: "Acertos/erros por aluno em uma prova do professor.",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        prova_id: { type: "integer" },
      },
      required: ["prova_id"],
      additionalProperties: false,
    },
  },
  {
    name: "ranking_erros_prova_professor",
    description: "Questões mais erradas de uma prova do professor.",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        prova_id: { type: "integer" },
        limite: { type: "integer" },
      },
      required: ["prova_id"],
      additionalProperties: false,
    },
  },
  {
    name: "saude_turmas_professor",
    description: "Saúde educacional das turmas do professor (crítico/atenção/saudável).",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        professor_nome: { type: "string" },
        turma_nome: { type: "string" },
        ano_letivo_id: { type: "integer" },
        nivel: { type: "string" },
      },
      additionalProperties: false,
    },
  },
];

const server = new Server(
  { name: "educatudo-provas-professor", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const name = req.params.name;
  const args = req.params.arguments || {};
  try {
    let result;
    switch (name) {
      case "buscar_professores":
        result = await postTool({ tool: "buscar_professores", termo: args.termo });
        break;
      case "listar_turmas_professor":
      case "listar_provas_professor":
      case "resumo_provas_professor":
      case "saude_turmas_professor":
        result = await postTool({ tool: name, ...args });
        break;
      case "detalhar_prova_professor":
        result = await postTool({
          tool: "detalhar_prova_professor",
          professor_id: args.professor_id,
          prova_id: args.prova_id,
        });
        break;
      case "ranking_erros_prova_professor":
        result = await postTool({
          tool: "ranking_erros_prova_professor",
          professor_id: args.professor_id,
          prova_id: args.prova_id,
          limite: args.limite,
        });
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
