#!/usr/bin/env node
/**
 * MCP Server — Assistente de Boletim (EducaTudo)
 *
 * Expõe tools que chamam os endpoints admin da escola.
 *
 * Env:
 *   EDUCATUDO_BASE_URL  ex.: http://localhost:8000
 *   EDUCATUDO_COOKIE    cookie de sessão admin (PHPSESSID=...)
 *   EDUCATUDO_CSRF      token CSRF da sessão (mesmo valor de _token nos forms)
 *
 * Cursor mcp.json exemplo:
 * {
 *   "mcpServers": {
 *     "educatudo-boletim": {
 *       "command": "node",
 *       "args": ["/caminho/src/mcp/boletim-assistente/src/index.js"],
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

function assertConfig() {
  if (!BASE) throw new Error("Defina EDUCATUDO_BASE_URL");
  if (!COOKIE) throw new Error("Defina EDUCATUDO_COOKIE (sessão admin)");
  if (!CSRF) throw new Error("Defina EDUCATUDO_CSRF (_token da sessão)");
}

async function postForm(path, fields = {}) {
  assertConfig();
  const body = new URLSearchParams();
  body.set("_token", CSRF);
  for (const [k, v] of Object.entries(fields)) {
    if (v === undefined || v === null) continue;
    body.set(k, String(v));
  }
  const res = await fetch(`${BASE}${path}`, {
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

async function getJson(path) {
  assertConfig();
  const res = await fetch(`${BASE}${path}`, {
    method: "GET",
    headers: {
      Cookie: COOKIE,
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
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
    name: "listar_tipos_avaliacao",
    description:
      "Lista tipos de avaliação dos Eventos de Prova (Semanal, Bimestral, ENAC, Trabalho…).",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_turmas",
    description: "Lista turmas ativas da escola (para vincular na regra de boletim).",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_materias",
    description: "Lista matérias da escola (vínculo e agrupamento group_line).",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "listar_regras",
    description: "Lista eventos/regras de boletim já cadastrados (para editar).",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
  {
    name: "obter_regra",
    description: "Obtém uma regra de boletim completa (componentes, fórmula, escopo).",
    inputSchema: {
      type: "object",
      properties: {
        regra_id: { type: "integer", description: "ID da regra" },
      },
      required: ["regra_id"],
      additionalProperties: false,
    },
  },
  {
    name: "listar_eventos_prova",
    description: "Lista Eventos de Prova (blocos), opcionalmente filtrados por tipo_avaliacao_id.",
    inputSchema: {
      type: "object",
      properties: {
        tipo_avaliacao_id: { type: "integer" },
      },
      additionalProperties: false,
    },
  },
  {
    name: "resolver_blocos_por_tipo",
    description:
      "Resolve 'prova semanal' / id de tipo → IDs de eventos no intervalo de datas.",
    inputSchema: {
      type: "object",
      properties: {
        tipo: {
          type: "string",
          description: "ID numérico ou nome parcial (ex.: semanal, Prova Bimestral)",
        },
        data_inicio: { type: "string", description: "YYYY-MM-DD ou dd/mm/aaaa" },
        data_fim: { type: "string", description: "YYYY-MM-DD ou dd/mm/aaaa" },
      },
      required: ["tipo"],
      additionalProperties: false,
    },
  },
  {
    name: "propor_regra_nl",
    description:
      "Envia pedido em linguagem natural ao Assistente de Boletim. Retorna mensagem + rascunho JSON (não salva sozinho — usuário confirma no admin).",
    inputSchema: {
      type: "object",
      properties: {
        mensagem: { type: "string" },
        regra_id: {
          type: "integer",
          description: "Se estiver editando uma regra existente",
        },
        historico: {
          type: "array",
          items: {
            type: "object",
            properties: {
              role: { type: "string" },
              content: { type: "string" },
            },
          },
        },
      },
      required: ["mensagem"],
      additionalProperties: false,
    },
  },
  {
    name: "contexto_catalogo",
    description: "Snapshot do catálogo (tipos, turmas, matérias, regras) para grounding do LLM.",
    inputSchema: { type: "object", properties: {}, additionalProperties: false },
  },
];

const server = new Server(
  { name: "educatudo-boletim-assistente", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};

  try {
    let result;
    switch (name) {
      case "listar_tipos_avaliacao":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "listar_tipos_avaliacao",
        });
        break;
      case "listar_turmas":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "listar_turmas",
        });
        break;
      case "listar_materias":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "listar_materias",
        });
        break;
      case "listar_regras":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "listar_regras",
        });
        break;
      case "obter_regra":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "obter_regra",
          regra_id: args.regra_id,
        });
        break;
      case "listar_eventos_prova":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "listar_eventos_prova",
          tipo_avaliacao_id: args.tipo_avaliacao_id || "",
        });
        break;
      case "resolver_blocos_por_tipo":
        result = await postForm("/admin/boletim-configuracao/assistente/ferramenta", {
          tool: "resolver_blocos_por_tipo",
          tipo: args.tipo,
          data_inicio: args.data_inicio || "",
          data_fim: args.data_fim || "",
        });
        break;
      case "propor_regra_nl":
        result = await postForm("/admin/boletim-configuracao/assistente/mensagem", {
          mensagem: args.mensagem,
          regra_id: args.regra_id || 0,
          historico: JSON.stringify(args.historico || []),
        });
        break;
      case "contexto_catalogo":
        result = await getJson("/admin/boletim-configuracao/assistente/contexto");
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
