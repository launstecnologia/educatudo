"""Cliente OpenAI centralizado: chat completions, vector stores e responses
com file_search.

SDK alvo: `openai==2.43.0` (testado em produção — a versão 1.57.4 originalmente
pinada NÃO tinha `client.vector_stores`/`client.responses` top-level; ambos só
existem a partir de uma versão posterior do SDK, confirmado rodando ao vivo).
API de Vector Stores usada: `client.vector_stores.*` (top-level). Upload de
arquivo + indexação:
`client.vector_stores.files.upload_and_poll(vector_store_id=..., file=...)`.
Busca com citação de páginas: Responses API (`client.responses.create`) com
a tool `{"type": "file_search", "vector_store_ids": [...]}`.
"""
from __future__ import annotations

import json
import logging
import re

from openai import OpenAI

from app.config import get_settings

logger = logging.getLogger("apostila_ai.openai_service")

_client: OpenAI | None = None


def get_client() -> OpenAI:
    global _client
    if _client is None:
        settings = get_settings()
        _client = OpenAI(api_key=settings.openai_api_key)
    return _client


EXERCISE_SYSTEM_PROMPT = """Você é um analisador pedagógico.

Analise o conteúdo abaixo e extraia todos os exercícios encontrados.

Retorne apenas JSON válido no formato:

{
  "exercicios": [
    {
      "pagina": 1,
      "capitulo": null,
      "tema": null,
      "tipo": "objetiva|discursiva|verdadeiro_falso|associacao|outro",
      "enunciado": "",
      "alternativas": [],
      "gabarito": null,
      "dificuldade": "facil|media|dificil|nao_identificada"
    }
  ]
}

Regras:
- Não invente exercícios.
- Extraia somente o que estiver no texto.
- Se não houver exercício, retorne {"exercicios":[]}.
- Preserve o enunciado original quando possível.
- Informe a página correta."""


CHAT_SYSTEM_PROMPT = """Você é a IA da Apostila do EducaTudo.

Responda somente com base no contexto fornecido da apostila.
Sempre cite as páginas usadas.
Se a informação não estiver no contexto, diga:
"Não encontrei essa informação na apostila."

Regras:
- Não invente conteúdo.
- Seja claro e pedagógico.
- Quando listar exercícios, informe página, tema e tipo.
- Quando o professor pedir uma prova, use apenas conteúdos e exercícios do contexto.
- Quando pedir resumo, organize por tópicos.
- Quando pedir explicação, use linguagem de professor.
- Se o professor pedir para "criar", "gerar" ou "fazer" um slide, apresentação
  ou slider, NÃO escreva um rascunho de slides aqui no chat — isso cria a
  falsa impressão de que algo foi gerado de verdade. Em vez disso, responda
  apenas orientando a usar o botão "Gerar slides de um capítulo" abaixo do
  chat, que gera a apresentação de verdade (com link real)."""


def _strip_markdown_fences(content: str) -> str:
    content = content.strip()
    fence_match = re.match(r"^```(?:json)?\s*(.*?)\s*```$", content, re.DOTALL)
    if fence_match:
        return fence_match.group(1).strip()
    return content


def detectar_exercicios(texto_pagina: str, numero_pagina: int) -> list[dict]:
    """Chama o modelo de chat para detectar exercícios em um trecho de texto
    referente a uma página (ou pequeno grupo de páginas).
    """
    settings = get_settings()
    client = get_client()

    user_content = (
        f"Página {numero_pagina}. Conteúdo:\n\n{texto_pagina}"
    )

    try:
        completion = client.chat.completions.create(
            model=settings.openai_model,
            messages=[
                {"role": "system", "content": EXERCISE_SYSTEM_PROMPT},
                {"role": "user", "content": user_content},
            ],
            temperature=0,
        )
    except Exception:
        logger.exception(
            "erro_chamada_openai_exercicios pagina=%s", numero_pagina
        )
        raise

    raw_content = completion.choices[0].message.content or "{}"
    cleaned = _strip_markdown_fences(raw_content)

    try:
        data = json.loads(cleaned)
    except json.JSONDecodeError:
        logger.warning(
            "resposta_json_invalida_exercicios pagina=%s", numero_pagina
        )
        return []

    exercicios = data.get("exercicios", [])
    if not isinstance(exercicios, list):
        return []
    return exercicios


def criar_vector_store(nome: str) -> str:
    client = get_client()
    vector_store = client.vector_stores.create(name=nome)
    return vector_store.id


def remover_vector_store(vector_store_id: str) -> None:
    """Remove um vector store OpenAI (best-effort — falha não interrompe fluxo)."""
    if not vector_store_id:
        return
    client = get_client()
    client.vector_stores.delete(vector_store_id)


def adicionar_textos_ao_vector_store_em_lote(
    vector_store_id: str, paginas: list[tuple[int, str]]
) -> None:
    """Sobe o texto de várias páginas como arquivos no vector store, em UM
    lote (file_batches.upload_and_poll), em vez de subir e esperar a indexação
    de cada página individualmente. Subir+indexar página por página (a
    abordagem anterior) faz uma chamada e um polling completos por arquivo —
    para apostilas de centenas de páginas isso demorava horas. O upload em
    lote sobe todos os arquivos com concorrência interna e faz polling do
    lote como um todo, muito mais rápido.

    `paginas` é uma lista de tuplas (numero_pagina, texto); páginas com texto
    vazio são ignoradas.
    """
    arquivos = []
    for numero_pagina, texto in paginas:
        if not texto.strip():
            continue
        filename = f"pagina_{numero_pagina:04d}.txt"
        arquivos.append((filename, texto.encode("utf-8")))

    if not arquivos:
        return

    client = get_client()
    client.vector_stores.file_batches.upload_and_poll(
        vector_store_id=vector_store_id,
        files=arquivos,
    )


def _limpar_marcadores_citacao(texto: str) -> str:
    """Remove marcadores internos de citação do file_search (ex.: `【2:pagina_0014.txt】`)
    que às vezes vêm embutidos no texto da resposta. As páginas já são
    extraídas separadamente (ver `paginas_usadas`/`fontes`), então esses
    marcadores no meio do texto são só ruído para quem lê.
    """
    return re.sub(r"【[^】]*】", "", texto).strip()


def _montar_input_messages(
    pergunta: str, historico: list[tuple[str, str]] | None = None
) -> list[dict]:
    input_messages: list[dict] = []
    for pergunta_anterior, resposta_anterior in (historico or []):
        input_messages.append({"role": "user", "content": pergunta_anterior})
        input_messages.append({"role": "assistant", "content": resposta_anterior})
    input_messages.append({"role": "user", "content": pergunta})
    return input_messages


def _extrair_citacoes_da_response(response) -> list[dict]:  # noqa: ANN001
    citacoes: list[dict] = []
    try:
        for item in response.output:
            if getattr(item, "type", None) == "file_search_call":
                results = getattr(item, "results", None) or []
                for result in results:
                    citacoes.append(
                        {
                            "filename": getattr(result, "filename", None),
                            "texto": (getattr(result, "text", "") or "")[:500],
                        }
                    )
            if getattr(item, "type", None) == "message":
                for content_block in getattr(item, "content", []) or []:
                    annotations = getattr(content_block, "annotations", []) or []
                    for annotation in annotations:
                        filename = getattr(annotation, "filename", None)
                        if filename:
                            citacoes.append({"filename": filename, "texto": ""})
    except Exception:
        logger.warning("falha_ao_extrair_citacoes_file_search", exc_info=True)
    return citacoes


def _montar_instructions(resumo_sessao: str | None = None) -> str:
    if resumo_sessao and resumo_sessao.strip():
        return (
            CHAT_SYSTEM_PROMPT
            + "\n\nResumo da conversa até aqui (use para continuidade, mas responda com base na apostila):\n"
            + resumo_sessao.strip()
        )
    return CHAT_SYSTEM_PROMPT


def gerar_resumo_conversa_sessao(mensagens: list[tuple[str, str]]) -> str:
    """Gera resumo rolling de uma sessão de chat para memória longa."""
    if not mensagens:
        return ""

    settings = get_settings()
    client = get_client()

    linhas: list[str] = []
    for pergunta, resposta in mensagens:
        linhas.append(f"Usuário: {pergunta}")
        linhas.append(f"Assistente: {resposta}")
    historico_texto = "\n".join(linhas)[-12000:]

    completion = client.chat.completions.create(
        model=settings.openai_model,
        messages=[
            {
                "role": "system",
                "content": (
                    "Resuma a conversa abaixo em português, em até 8 bullet points objetivos. "
                    "Preserve temas discutidos, pedidos do usuário e conclusões importantes. "
                    "Não invente informações."
                ),
            },
            {"role": "user", "content": historico_texto},
        ],
        temperature=0.2,
    )
    return (completion.choices[0].message.content or "").strip()


def responder_com_file_search(
    vector_store_id: str,
    pergunta: str,
    historico: list[tuple[str, str]] | None = None,
    resumo_sessao: str | None = None,
) -> tuple[str, list[dict]]:
    """Usa a Responses API com a tool file_search apontando para o vector
    store da apostila. Retorna (texto_resposta, lista_de_citacoes) onde cada
    citação é um dict com possíveis chaves 'filename' e 'texto'.

    `historico` (opcional) é uma lista de (pergunta, resposta) anteriores
    nesta mesma conversa, em ordem cronológica — dá ao modelo contexto de
    continuidade para entender follow-ups como "pode gerar" ou "explica
    melhor" sem o professor precisar repetir o assunto.
    """
    settings = get_settings()
    client = get_client()
    input_messages = _montar_input_messages(pergunta, historico)

    response = client.responses.create(
        model=settings.openai_model,
        instructions=_montar_instructions(resumo_sessao),
        input=input_messages,
        tools=[
            {
                "type": "file_search",
                "vector_store_ids": [vector_store_id],
            }
        ],
        include=["file_search_call.results"],
    )

    resposta_texto = _limpar_marcadores_citacao(response.output_text or "")
    citacoes = _extrair_citacoes_da_response(response)
    return resposta_texto, citacoes


def iterar_resposta_com_file_search(
    vector_store_id: str,
    pergunta: str,
    historico: list[tuple[str, str]] | None = None,
    resumo_sessao: str | None = None,
):
    """Generator que emite tuplas (tipo_evento, payload) durante o streaming
    da Responses API com file_search.

    tipos emitidos:
    - ("status", "consultando") — file_search em andamento
    - ("token", str) — fragmento de texto da resposta
    - ("concluido", {"resposta": str, "citacoes": list[dict]}) — fim com metadados
    """
    settings = get_settings()
    client = get_client()
    input_messages = _montar_input_messages(pergunta, historico)

    stream = client.responses.create(
        model=settings.openai_model,
        instructions=_montar_instructions(resumo_sessao),
        input=input_messages,
        tools=[
            {
                "type": "file_search",
                "vector_store_ids": [vector_store_id],
            }
        ],
        include=["file_search_call.results"],
        stream=True,
    )

    resposta_partes: list[str] = []
    response_final = None

    try:
        for event in stream:
            tipo = getattr(event, "type", None)

            if tipo in (
                "response.file_search_call.in_progress",
                "response.file_search_call.searching",
            ):
                yield ("status", "consultando")

            if tipo == "response.output_text.delta":
                delta = getattr(event, "delta", "") or ""
                if delta:
                    resposta_partes.append(delta)
                    yield ("token", delta)

            if tipo == "response.completed":
                response_final = getattr(event, "response", None)
    finally:
        close_fn = getattr(stream, "close", None)
        if callable(close_fn):
            close_fn()

    if response_final is not None:
        resposta_texto = _limpar_marcadores_citacao(response_final.output_text or "")
        citacoes = _extrair_citacoes_da_response(response_final)
    else:
        resposta_texto = _limpar_marcadores_citacao("".join(resposta_partes))
        citacoes = []

    yield ("concluido", {"resposta": resposta_texto, "citacoes": citacoes})


SLIDES_SYSTEM_PROMPT = """Você é um assistente pedagógico que redige roteiros de slides.

Use SOMENTE o conteúdo fornecido no contexto (extraído de uma apostila) para
montar o roteiro. Não invente fatos, dados ou exemplos que não estejam no
contexto.

Formato de saída: Markdown, com cada slide separado por uma linha contendo
apenas "---". O primeiro slide é sempre um título. Cada slide de conteúdo deve
ter um título curto (##) seguido de bullets objetivos. Não inclua um slide de
fonte/referência — isso é adicionado separadamente depois do seu roteiro.

Regras:
- Gere exatamente {num_slides} slides (incluindo o de título).
- Seja didático e direto, frases curtas, adequado para apresentação em aula.
- Se as instruções do professor pedirem um enfoque específico, priorize-o,
  mas sem extrapolar o conteúdo fornecido."""


def redigir_slides_markdown(
    contexto: str,
    capitulo_ou_tema: str,
    instrucoes: str | None,
    num_slides: int,
) -> str:
    """Usa um chat completion para transformar o conteúdo recuperado da apostila
    (via RAG) em um roteiro de slides em Markdown, grounded no contexto fornecido.
    """
    settings = get_settings()
    client = get_client()

    user_content = f"Tema/capítulo solicitado: {capitulo_ou_tema}\n\n"
    if instrucoes:
        user_content += f"Instruções adicionais do professor: {instrucoes}\n\n"
    user_content += f"Contexto extraído da apostila:\n\n{contexto}"

    system_prompt = SLIDES_SYSTEM_PROMPT.format(num_slides=num_slides)

    completion = client.chat.completions.create(
        model=settings.openai_model,
        messages=[
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_content},
        ],
        temperature=0.3,
    )

    return completion.choices[0].message.content or ""


def extrair_numero_pagina_do_filename(filename: str | None) -> int | None:
    if not filename:
        return None
    match = re.search(r"pagina_(\d+)", filename)
    if match:
        return int(match.group(1))
    return None
