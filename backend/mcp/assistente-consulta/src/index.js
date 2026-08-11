#!/usr/bin/env node
/**
 * MCP Server — Consultas ampliadas do Assistente (EducaTudo)
 * Turma, bloco de prova, jornadas do professor, boletim e faltas.
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
const ENDPOINT = "/admin/consulta-assistente/mcp/ferramenta";

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
    // Ainda devolve o JSON quando há candidatos (desambiguação).
    if (json?.data?.candidatos) {
      return json;
    }
    throw new Error(json.error || `HTTP ${res.status}`);
  }
  return json;
}

const TOOLS = [
  {
    name: "saude_turma",
    description: "Saúde educacional de uma turma (KPIs crítico/atenção + alunos em atenção).",
    inputSchema: {
      type: "object",
      properties: {
        turma_id: { type: "integer" },
        turma_nome: { type: "string" },
        ano_letivo_id: { type: "integer" },
        nivel: { type: "string" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "resumo_provas_turma",
    description: "Totais de acertos/erros das provas dos alunos de uma turma, por matéria.",
    inputSchema: {
      type: "object",
      properties: {
        turma_id: { type: "integer" },
        turma_nome: { type: "string" },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "buscar_blocos",
    description: "Busca blocos de prova por título e/ou turma.",
    inputSchema: {
      type: "object",
      properties: {
        titulo: { type: "string" },
        turma_id: { type: "integer" },
        turma_nome: { type: "string" },
        limite: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "resultados_bloco",
    description: "Dashboard de resultados de um bloco: indicadores, questões mais erradas, alunos em atenção.",
    inputSchema: {
      type: "object",
      properties: {
        bloco_id: { type: "integer" },
        titulo: { type: "string" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "resumo_jornadas_professor",
    description: "Consolidado de jornadas criadas pelo professor (totais + alunos em atenção).",
    inputSchema: {
      type: "object",
      properties: {
        professor_id: { type: "integer" },
        professor_nome: { type: "string" },
        turma_id: { type: "integer" },
        turma_nome: { type: "string" },
        ano_letivo: { type: "integer" },
        somente_atencao: { type: "boolean" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "boletim_aluno",
    description: "Boletins gerados do aluno (visão coordenação).",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        aluno_nome: { type: "string" },
        turma_nome: { type: "string" },
        exibir_em: { type: "string", description: "boletim ou notas" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "faltas_aluno",
    description: "Frequência/faltas do aluno no período (diário de classe).",
    inputSchema: {
      type: "object",
      properties: {
        aluno_id: { type: "integer" },
        aluno_nome: { type: "string" },
        turma_nome: { type: "string" },
        data_inicio: { type: "string" },
        data_fim: { type: "string" },
        ano_letivo_id: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
];

const server = new Server(
  { name: "educatudo-assistente-consulta", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};
  const data = await postTool({ tool: name, ...args });
  return {
    content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
  };
});

const transport = new StdioServerTransport();
await server.connect(transport);
