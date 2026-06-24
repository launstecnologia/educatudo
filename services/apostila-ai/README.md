# apostila-ai — IA da Apostila

Microsserviço Python (FastAPI) independente, separado do app principal PHP
(EducaTudo). Responsável por:

- Extrair texto página-a-página de PDFs de apostilas (PyMuPDF)
- Dividir o conteúdo em chunks (~800-1200 tokens estimados) por página
- Detectar exercícios via OpenAI (chat completions) e salvar estruturado
- Responder perguntas de professores sobre o conteúdo da apostila (RAG via
  OpenAI Vector Store + Responses API com `file_search`), citando páginas
- Montar provas reaproveitando exercícios já extraídos da apostila

Este serviço **não substitui** a tabela `modulos_apostilas` já existente no
PHP — todas as tabelas novas usam o prefixo `apostilas_ia` / `apostila_ia_*`
para evitar qualquer colisão de nomenclatura.

## Stack

Python 3.11, FastAPI, Uvicorn, PyMuPDF (`fitz`), `openai` 1.x (SDK client
style), Pydantic v2, SQLAlchemy 2.x, PyMySQL.

## Instalação local (sem Docker)

```bash
cd services/apostila-ai
python3.11 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# editar .env: OPENAI_API_KEY, DATABASE_URL, INTERNAL_API_KEY
uvicorn app.main:app --host 0.0.0.0 --port 8088 --reload
```

## Configuração do `.env`

Copie `.env.example` para `.env` e ajuste:

- `OPENAI_API_KEY` — chave da OpenAI (obrigatória para processar e responder chat)
- `DATABASE_URL` — string SQLAlchemy `mysql+pymysql://user:senha@host:3306/educatudo`
  apontando para o **mesmo banco MySQL** usado pela plataforma PHP
- `INTERNAL_API_KEY` — chave compartilhada que o PHP deve enviar no header
  `X-Internal-Api-Key` em toda chamada (exceto `/health`)
- `STORAGE_PATH` — diretório base de storage dentro do container (`/app/app/storage`)
- `MAX_UPLOAD_MB` — limite de tamanho de PDF aceito para processamento

## Rodando via Docker

```bash
cd services/apostila-ai
cp .env.example .env   # ajustar valores
docker compose up --build
```

O serviço sobe em `http://localhost:8088`. O volume `./app/storage` é
montado no container para persistir uploads/páginas extraídas localmente.

## Testando os endpoints

Todas as rotas (exceto `/health`) exigem o header:

```
X-Internal-Api-Key: <valor configurado em INTERNAL_API_KEY>
```

### Healthcheck (sem auth)

```bash
curl http://localhost:8088/health
# {"status":"ok","service":"apostila-ai"}
```

### Processar uma apostila

```bash
curl -X POST http://localhost:8088/apostilas/1/processar \
  -H "Content-Type: application/json" \
  -H "X-Internal-Api-Key: trocar_essa_chave" \
  -d '{
        "pdf_path": "/app/app/storage/uploads/biologia_8ano.pdf",
        "titulo": "Apostila de Biologia 8º ano"
      }'
```

Resposta esperada (sucesso):

```json
{
  "apostila_id": 1,
  "status": "pronto",
  "total_paginas": 42,
  "paginas_sem_texto": 0,
  "chunks_criados": 6,
  "exercicios_detectados": 18,
  "vector_store_id": "vs_xxx",
  "erro": null,
  "tempo_processamento_segundos": 37.21
}
```

O PDF precisa existir no caminho informado e ser um PDF real (validação por
magic bytes). Caminhos com `..` são rejeitados (proteção contra path
traversal).

### Perguntar sobre o conteúdo (chat/RAG)

```bash
curl -X POST http://localhost:8088/chat/apostila \
  -H "Content-Type: application/json" \
  -H "X-Internal-Api-Key: trocar_essa_chave" \
  -d '{
        "apostila_id": 1,
        "professor_id": 10,
        "pergunta": "Quais exercícios existem sobre fotossíntese?"
      }'
```

Resposta:

```json
{
  "resposta": "Na apostila há...",
  "paginas_usadas": [18, 19, 22],
  "fontes": [{"pagina": 18, "trecho": "..."}]
}
```

### Listar exercícios detectados

```bash
curl "http://localhost:8088/apostilas/1/exercicios?tema=fotossintese" \
  -H "X-Internal-Api-Key: trocar_essa_chave"
```

### Gerar uma prova a partir do material já processado

```bash
curl -X POST http://localhost:8088/apostilas/1/gerar-prova \
  -H "Content-Type: application/json" \
  -H "X-Internal-Api-Key: trocar_essa_chave" \
  -d '{
        "professor_id": 10,
        "tema": "fotossíntese",
        "quantidade": 10,
        "nivel": "media",
        "incluir_gabarito": true
      }'
```

## Integração com o EducaTudo (PHP)

- O PHP é responsável pelo upload físico do PDF (validação de MIME, prefixo
  de tenant no path, etc., conforme as regras do projeto principal).
- Depois do upload, o PHP chama `POST /apostilas/{id}/processar` deste
  serviço via HTTP, passando o caminho absoluto do PDF já salvo em disco.
- Este serviço conecta-se ao **mesmo banco MySQL** usado pela plataforma
  PHP (`DATABASE_URL`), lendo/escrevendo apenas nas tabelas prefixadas
  `apostilas_ia` / `apostila_ia_*` — nunca toca em `modulos_apostilas` ou
  qualquer outra tabela do domínio principal.
- Toda chamada HTTP entre PHP e este serviço deve enviar o header
  `X-Internal-Api-Key` com o mesmo valor configurado em `INTERNAL_API_KEY`
  neste serviço.
- O schema real das tabelas (`CREATE TABLE`) deve ser criado via migration
  no repositório PHP (`src/database/migrations/`), seguindo exatamente os
  tipos e nomes de coluna espelhados em `app/models/db_models.py` deste
  serviço, para manter os dois lados sincronizados.

## Limitações conhecidas / fora de escopo nesta versão (MVP)

- OCR de páginas escaneadas **não está implementado** — páginas sem texto
  extraível são marcadas e logadas, mas o texto fica vazio. Os pacotes
  `tesseract-ocr` já estão na imagem Docker para facilitar essa adição
  futura.
- Processamento é síncrono (sem fila/Celery/Redis) — para apostilas muito
  grandes, considerar mover o processamento para um worker assíncrono no
  futuro.
- Estimativa de tokens para chunking usa uma heurística simples
  (`len(texto.split()) * 1.3`), não um tokenizer exato (`tiktoken` não foi
  incluído para manter a imagem Docker mais leve).
