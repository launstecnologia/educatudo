/**
 * EducaTudo - WebSocket server for presence online per school (tenant).
 * Clients send { type: "login", escola, usuario_id, nome, tipo } on connect.
 * Server broadcasts { type: "master_update", data: { [escola]: { alunos, professores } } } to all.
 */

const WebSocket = require('ws');

const PORT = process.env.PORT || 3001;

// escolas[escola] = { alunos: { [usuario_id]: { client, nome } }, professores: { ... } }
const escolas = {};

const wss = new WebSocket.Server({ port: PORT });

function getDashboardPayload() {
  const resumo = {};
  for (const escola in escolas) {
    resumo[escola] = {
      alunos: Object.keys(escolas[escola].alunos).length,
      professores: Object.keys(escolas[escola].professores).length
    };
  }
  return JSON.stringify({ type: 'dashboard', data: resumo });
}

function broadcastMaster() {
  const payload = getDashboardPayload();
  wss.clients.forEach((client) => {
    if (client.readyState === 1) {
      client.send(payload);
    }
  });
}

function removeClient(client) {
  const escola = client._escola;
  const usuario_id = client._usuario_id;
  const tipo = client._tipo;
  if (!escola || usuario_id == null || !tipo) return;
  const e = escolas[escola];
  if (!e) return;
  const bucket = tipo === 'aluno' ? e.alunos : e.professores;
  if (bucket[usuario_id]) {
    delete bucket[usuario_id];
  }
  client._escola = null;
  client._usuario_id = null;
  client._tipo = null;
  broadcastMaster();
}

wss.on('connection', (ws) => {
  // Enviar estado atual só para este cliente (evita race: ao dar F5 o master recebe o resumo)
  setImmediate(() => {
    if (ws.readyState === 1) {
      ws.send(getDashboardPayload());
    }
  });

  ws.on('message', (data) => {
    try {
      const msg = JSON.parse(data.toString());
      if (msg.type === 'get_dashboard') {
        if (ws.readyState === 1) ws.send(getDashboardPayload());
        return;
      }
      if (msg.type !== 'login') return;
      const escola = String(msg.escola || '').trim().toLowerCase();
      const usuario_id = String(msg.usuario_id ?? '');
      const nome = String(msg.nome ?? '');
      const tipo = (msg.tipo === 'professor') ? 'professor' : 'aluno';
      if (!escola || !usuario_id) return;

      if (!escolas[escola]) {
        escolas[escola] = { alunos: {}, professores: {} };
      }
      const bucket = tipo === 'aluno' ? escolas[escola].alunos : escolas[escola].professores;
      bucket[usuario_id] = { client: ws, nome };
      ws._escola = escola;
      ws._usuario_id = usuario_id;
      ws._tipo = tipo;
      broadcastMaster();
    } catch (e) {
      // ignore invalid json
    }
  });

  ws.on('close', () => {
    removeClient(ws);
  });

  ws.on('error', () => {
    removeClient(ws);
  });
});

wss.on('listening', () => {
  console.log('EducaTudo WS presence server listening on port', PORT);
});
