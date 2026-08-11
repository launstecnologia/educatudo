<?php
$ocorrencias = is_array($ocorrencias ?? null) ? $ocorrencias : [];
$csrf_token = $csrf_token ?? '';
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-xl font-semibold text-gray-900">Ocorrências</h2>
        <p class="text-sm text-gray-500">Registre ocorrências com áudio e vincule vários alunos.</p>
    </div>
    <button type="button" id="btnGravarOcorrencia" class="px-3 py-2 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700">
        Gravar ocorrência
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8">
    <form id="ocorrenciaForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Data da ocorrência</label>
            <input type="datetime-local" name="data_ocorrencia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Título (incidente)</label>
            <input type="text" name="titulo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex: Briga no intervalo" required>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Detalhe do incidente</label>
            <textarea name="detalhe" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Ex: Chingou o colega / Bateu / Roubou lanche" required></textarea>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Nível de gravidade</label>
            <select name="nivel_gravidade" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                <option value="">Selecione</option>
                <option value="leve">Leve</option>
                <option value="moderado">Moderado</option>
                <option value="grave">Grave</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Atitude da coordenação</label>
            <select name="atitude_coordenacao" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Selecione</option>
                <option value="advertencia">Advertência</option>
                <option value="suspensao">Suspensão</option>
                <option value="orientacao">Orientação</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Retorno para conversar</label>
            <input type="date" name="retorno_em" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="enviarPais" name="enviar_pais" class="h-4 w-4">
            <label for="enviarPais" class="text-xs text-gray-600">Disponibilizar no acesso dos pais</label>
        </div>
        <div class="md:col-span-2 border-t pt-4">
            <div class="flex flex-col md:flex-row md:items-center gap-3">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Áudio do coordenador (opcional)</label>
                    <input type="file" id="ocorrenciaAudio" accept="audio/*" class="w-full text-sm">
                    <audio id="ocorrenciaAudioPlayer" class="mt-2 w-full hidden" controls></audio>
                </div>
            </div>
            <textarea id="ocorrenciaTexto" rows="2" class="mt-2 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm hidden" placeholder="Transcrição ou resumo do áudio"></textarea>
            <div id="gravacaoStatus" class="mt-2 text-xs text-gray-500 hidden"></div>
        </div>

        <div class="md:col-span-2 border-t pt-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">Alunos envolvidos</h4>
            <div class="flex flex-col md:flex-row gap-3 mb-3">
                <input type="text" id="alunoBusca" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Buscar aluno pelo nome">
                <button type="button" id="btnBuscarAluno" class="px-3 py-2 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Buscar</button>
            </div>
            <div id="alunoResultados" class="space-y-2 mb-4 text-sm text-gray-700"></div>
            <div>
                <p class="text-xs font-medium text-gray-600 mb-2">Selecionados</p>
                <div id="alunoSelecionados" class="space-y-2"></div>
            </div>
        </div>

        <div class="md:col-span-2">
            <div id="sugestoesBox" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-xs font-medium text-gray-600 mb-2">Sugestões da IA (confirme abaixo)</p>
                <div id="sugestoesAlunos" class="space-y-2 mb-4"></div>
                <div id="sugestoesTurmas" class="space-y-2"></div>
            </div>
        </div>

        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm hover:opacity-90">Salvar ocorrência</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700">Ocorrências registradas</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="px-4 py-2 text-left">Data</th>
                    <th class="px-4 py-2 text-left">Título</th>
                    <th class="px-4 py-2 text-left">Alunos</th>
                    <th class="px-4 py-2 text-left">Turmas</th>
                    <th class="px-4 py-2 text-left">Gravidade</th>
                    <th class="px-4 py-2 text-left">Pais</th>
                    <th class="px-4 py-2 text-left">Registrado por</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (empty($ocorrencias)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-500">Nenhuma ocorrência registrada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ocorrencias as $oc): ?>
                        <tr>
                            <td class="px-4 py-2 text-gray-700"><?= date('d/m/Y H:i', strtotime($oc['data_ocorrencia'])) ?></td>
                            <td class="px-4 py-2 text-gray-900">
                                <div class="font-medium"><?= htmlspecialchars($oc['titulo'] ?? '') ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($oc['detalhe'] ?? '') ?></div>
                            </td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($oc['alunos_nomes'] ?? '-') ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($oc['turmas_nomes'] ?? '-') ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= ucfirst($oc['nivel_gravidade'] ?? '') ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= !empty($oc['enviar_pais']) ? 'Sim' : 'Não' ?></td>
                            <td class="px-4 py-2 text-gray-700"><?= htmlspecialchars($oc['criado_por_nome'] ?? 'Admin') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const ocorrenciaForm = document.getElementById('ocorrenciaForm');
const ocorrenciaAudio = document.getElementById('ocorrenciaAudio');
const ocorrenciaAudioPlayer = document.getElementById('ocorrenciaAudioPlayer');
const ocorrenciaTexto = document.getElementById('ocorrenciaTexto');
const btnGravarOcorrencia = document.getElementById('btnGravarOcorrencia');
const gravacaoStatus = document.getElementById('gravacaoStatus');
const sugestoesBox = document.getElementById('sugestoesBox');
const sugestoesAlunos = document.getElementById('sugestoesAlunos');
const sugestoesTurmas = document.getElementById('sugestoesTurmas');
const alunoResultados = document.getElementById('alunoResultados');
const alunoSelecionados = document.getElementById('alunoSelecionados');
const btnBuscarAluno = document.getElementById('btnBuscarAluno');
const alunoBusca = document.getElementById('alunoBusca');

let mediaRecorder = null;
let gravando = false;
let audioChunks = [];
const alunosSelecionadosMap = new Map();

const dataInput = ocorrenciaForm.querySelector('input[name="data_ocorrencia"]');
if (dataInput && !dataInput.value) {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    dataInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

function renderSelecionados() {
    alunoSelecionados.innerHTML = '';
    document.querySelectorAll('input[name="alunos[]"]').forEach((el) => el.remove());
    if (alunosSelecionadosMap.size === 0) {
        alunoSelecionados.innerHTML = '<p class="text-xs text-gray-500">Nenhum aluno selecionado.</p>';
        return;
    }
    alunosSelecionadosMap.forEach((aluno) => {
        const item = document.createElement('div');
        item.className = 'flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm';
        item.innerHTML = `
            <div>
                <div class="text-gray-900">${aluno.nome}</div>
                <div class="text-xs text-gray-500">${aluno.turma_nome || '-'}</div>
            </div>
            <button type="button" data-id="${aluno.id}" class="text-red-600 text-xs">Remover</button>
        `;
        item.querySelector('button').addEventListener('click', () => {
            alunosSelecionadosMap.delete(aluno.id);
            renderSelecionados();
        });
        alunoSelecionados.appendChild(item);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'alunos[]';
        hidden.value = aluno.id;
        ocorrenciaForm.appendChild(hidden);
    });
}

function adicionarAlunoSelecionado(aluno) {
    if (!aluno || !aluno.id) return;
    alunosSelecionadosMap.set(String(aluno.id), {
        id: String(aluno.id),
        nome: aluno.nome,
        turma_nome: aluno.turma_nome
    });
    renderSelecionados();
}

btnBuscarAluno.addEventListener('click', async () => {
    const term = alunoBusca.value.trim();
    if (!term) return;
    const resp = await fetch(`<?= URL ?>/admin/ocorrencias/buscar-alunos?term=${encodeURIComponent(term)}`);
    const data = await resp.json();
    alunoResultados.innerHTML = '';
    if (!data.success || !data.alunos || data.alunos.length === 0) {
        alunoResultados.innerHTML = '<p class="text-xs text-gray-500">Nenhum aluno encontrado.</p>';
        return;
    }
    data.alunos.forEach((aluno) => {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm';
        row.innerHTML = `
            <div>
                <div class="text-gray-900">${aluno.nome}</div>
                <div class="text-xs text-gray-500">${aluno.turma_nome || '-'}</div>
            </div>
            <button type="button" class="text-indigo-600 text-xs">Adicionar</button>
        `;
        row.querySelector('button').addEventListener('click', () => adicionarAlunoSelecionado(aluno));
        alunoResultados.appendChild(row);
    });
});

ocorrenciaAudio.addEventListener('change', () => {
    if (ocorrenciaAudio.files && ocorrenciaAudio.files[0]) {
        const url = URL.createObjectURL(ocorrenciaAudio.files[0]);
        ocorrenciaAudioPlayer.src = url;
        ocorrenciaAudioPlayer.classList.remove('hidden');
        iniciarPipelineAudio(ocorrenciaAudio.files[0]);
    }
});

function normalizarSerie(texto) {
    if (!texto) return '';
    let normalized = texto;
    normalized = normalized.replace(/\b1(?:º|o)?\s*ano\b/gi, '1º Ano');
    normalized = normalized.replace(/\b2(?:º|o)?\s*ano\b/gi, '2º Ano');
    normalized = normalized.replace(/\b3(?:º|o)?\s*ano\b/gi, '3º Ano');
    normalized = normalized.replace(/\b4(?:º|o)?\s*ano\b/gi, '4º Ano');
    normalized = normalized.replace(/\b5(?:º|o)?\s*ano\b/gi, '5º Ano');
    normalized = normalized.replace(/\b6(?:º|o)?\s*ano\b/gi, '6º Ano');
    normalized = normalized.replace(/\b7(?:º|o)?\s*ano\b/gi, '7º Ano');
    normalized = normalized.replace(/\b8(?:º|o)?\s*ano\b/gi, '8º Ano');
    normalized = normalized.replace(/\b9(?:º|o)?\s*ano\b/gi, '9º Ano');
    normalized = normalized.replace(/\bprimeiro\s*ano\b/gi, '1º Ano');
    normalized = normalized.replace(/\bsegundo\s*ano\b/gi, '2º Ano');
    normalized = normalized.replace(/\bterceiro\s*ano\b/gi, '3º Ano');
    normalized = normalized.replace(/\bquarto\s*ano\b/gi, '4º Ano');
    normalized = normalized.replace(/\bquinto\s*ano\b/gi, '5º Ano');
    normalized = normalized.replace(/\bsexto\s*ano\b/gi, '6º Ano');
    normalized = normalized.replace(/\bsetimo\s*ano\b/gi, '7º Ano');
    normalized = normalized.replace(/\boitavo\s*ano\b/gi, '8º Ano');
    normalized = normalized.replace(/\bnono\s*ano\b/gi, '9º Ano');
    return normalized;
}

function calcularRetornoEmDias(texto) {
    const match = texto.match(/(?:daqui\s+a|em)\s+([a-z0-9]{1,12})\s+dias?/i);
    if (!match) return null;
    const raw = match[1].toLowerCase();
    const mapa = {
        'um': 1, 'uma': 1, 'dois': 2, 'duas': 2, 'tres': 3, 'três': 3,
        'quatro': 4, 'cinco': 5, 'seis': 6, 'sete': 7, 'oito': 8,
        'nove': 9, 'dez': 10, 'onze': 11, 'doze': 12
    };
    const dias = mapa[raw] ?? parseInt(raw, 10);
    if (Number.isNaN(dias)) return null;
    const dataBaseRaw = ocorrenciaForm.querySelector('input[name="data_ocorrencia"]').value;
    const dataBase = dataBaseRaw ? new Date(dataBaseRaw) : new Date();
    if (Number.isNaN(dataBase.getTime())) return null;
    dataBase.setDate(dataBase.getDate() + dias);
    const yyyy = dataBase.getFullYear();
    const mm = String(dataBase.getMonth() + 1).padStart(2, '0');
    const dd = String(dataBase.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function marcarEnviarPaisSeNecessario(payload, texto) {
    const checkbox = document.getElementById('enviarPais');
    if (!checkbox) return;
    const textoBase = `${texto || ''} ${payload?.detalhe || ''} ${payload?.titulo || ''}`.toLowerCase();
    const keywords = ['pais', 'responsavel', 'responsáveis', 'comunicar', 'avisar'];
    const precisa = keywords.some((k) => textoBase.includes(k));
    if (precisa) {
        checkbox.checked = true;
    }
}

function aplicarOcorrenciaPayload(payload, texto) {
    const textoNormalizado = normalizarSerie(texto || '');
    ocorrenciaTexto.value = textoNormalizado;
    if (payload.titulo) ocorrenciaForm.querySelector('input[name="titulo"]').value = normalizarSerie(payload.titulo);
    if (payload.detalhe) ocorrenciaForm.querySelector('textarea[name="detalhe"]').value = normalizarSerie(payload.detalhe);
    if (payload.nivel_gravidade) ocorrenciaForm.querySelector('select[name="nivel_gravidade"]').value = payload.nivel_gravidade;
    if (payload.atitude_coordenacao !== undefined) ocorrenciaForm.querySelector('select[name="atitude_coordenacao"]').value = payload.atitude_coordenacao;
    const retornoInput = ocorrenciaForm.querySelector('input[name="retorno_em"]');
    const retornoCalculado = calcularRetornoEmDias(textoNormalizado);
    if (retornoInput && retornoCalculado) {
        retornoInput.value = retornoCalculado;
    } else if (payload.retorno_em && retornoInput) {
        retornoInput.value = payload.retorno_em;
    }
    marcarEnviarPaisSeNecessario(payload, textoNormalizado);
}

function renderSugestoes(sugAlunos, sugTurmas, naoLocalizados = []) {
    sugestoesAlunos.innerHTML = '';
    sugestoesTurmas.innerHTML = '';
    if (naoLocalizados && naoLocalizados.length > 0) {
        const aviso = document.createElement('div');
        aviso.className = 'text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-3';
        aviso.textContent = `Não foi possível localizar: ${naoLocalizados.join(', ')}.`;
        sugestoesAlunos.appendChild(aviso);
    }
    if ((!sugAlunos || sugAlunos.length === 0) && (!sugTurmas || sugTurmas.length === 0)) {
        sugestoesBox.classList.add('hidden');
        return;
    }
    sugestoesBox.classList.remove('hidden');

    if (sugAlunos && sugAlunos.length > 0) {
        const title = document.createElement('div');
        title.className = 'text-xs font-medium text-gray-600';
        title.textContent = 'Alunos sugeridos';
        sugestoesAlunos.appendChild(title);
        sugAlunos.forEach((aluno) => {
            const turmaDb = (aluno.turma_nome || '').toString();
            const turmaIa = (aluno.turma_ia || '').toString();
            const conflito = turmaIa && turmaDb && turmaIa !== turmaDb;
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white';
            row.innerHTML = `
                <div>
                    <div class="text-gray-900">${aluno.nome}</div>
                    <div class="text-xs text-gray-500">
                        ${turmaDb || '-'}
                        ${turmaIa ? `<span class="ml-2 text-xs ${conflito ? 'text-red-600' : 'text-gray-500'}">(IA: ${turmaIa})</span>` : ''}
                    </div>
                    ${conflito ? `<div class="text-xs text-red-600 mt-1">Turma diverge da IA</div>` : ''}
                </div>
                <button type="button" class="text-indigo-600 text-xs">Adicionar</button>
            `;
            row.querySelector('button').addEventListener('click', () => adicionarAlunoSelecionado(aluno));
            sugestoesAlunos.appendChild(row);
        });
    }

    if (sugTurmas && sugTurmas.length > 0) {
        const title = document.createElement('div');
        title.className = 'text-xs font-medium text-gray-600 mt-4';
        title.textContent = 'Turmas sugeridas';
        sugestoesTurmas.appendChild(title);
        sugTurmas.forEach((turma) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white';
            row.innerHTML = `
                <div class="text-gray-900">${turma.nome}</div>
                <button type="button" class="text-indigo-600 text-xs">Adicionar alunos</button>
            `;
            row.querySelector('button').addEventListener('click', async () => {
                const resp = await fetch(`<?= URL ?>/admin/ocorrencias/buscar-alunos?turma_id=${turma.id}`);
                const data = await resp.json();
                if (data.success && data.alunos) {
                    data.alunos.forEach((aluno) => adicionarAlunoSelecionado(aluno));
                }
            });
            sugestoesTurmas.appendChild(row);
        });
    }
}

function iniciarPipelineAudio(audioFile) {
    const formData = new FormData();
    formData.append('_token', document.querySelector('input[name="_token"]').value);
    formData.append('audio', audioFile);

    if (gravacaoStatus) {
        gravacaoStatus.textContent = 'Transcrevendo e preenchendo com IA...';
        gravacaoStatus.classList.remove('hidden');
    }

    fetch(`<?= URL ?>/admin/ocorrencias/transcrever-audio`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao transcrever áudio');
        }
        ocorrenciaTexto.value = data.texto || '';
        const iaData = new FormData();
        iaData.append('_token', document.querySelector('input[name="_token"]').value);
        iaData.append('texto', ocorrenciaTexto.value);
        return fetch(`<?= URL ?>/admin/ocorrencias/auto-preencher`, {
            method: 'POST',
            body: iaData
        });
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao processar IA');
        }
        aplicarOcorrenciaPayload(data.data || {}, ocorrenciaTexto.value);
        renderSugestoes(data.sugestoes_alunos || [], data.sugestoes_turmas || [], data.alunos_nao_localizados || []);
    })
    .catch((err) => alert(err.message || 'Erro ao processar áudio'))
    .finally(() => {
        if (gravacaoStatus) {
            gravacaoStatus.textContent = 'Pronto para revisar e salvar.';
            gravacaoStatus.classList.remove('hidden');
        }
    });
}

ocorrenciaForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (alunosSelecionadosMap.size === 0) {
        alert('Selecione pelo menos um aluno.');
        return;
    }
    const formData = new FormData(ocorrenciaForm);
    fetch(`<?= URL ?>/admin/ocorrencias`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Erro ao salvar ocorrência');
        }
    })
    .catch(() => alert('Erro de conexão'));
});

if (btnGravarOcorrencia) {
    btnGravarOcorrencia.addEventListener('click', async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Seu navegador não suporta gravação de áudio.');
            return;
        }

        if (!gravando) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) {
                        audioChunks.push(event.data);
                    }
                };

                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const audioFile = new File([audioBlob], 'ocorrencia.webm', { type: 'audio/webm' });

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(audioFile);
                    ocorrenciaAudio.files = dataTransfer.files;

                    const url = URL.createObjectURL(audioBlob);
                    ocorrenciaAudioPlayer.src = url;
                    ocorrenciaAudioPlayer.classList.remove('hidden');
                    iniciarPipelineAudio(audioFile);
                };

                mediaRecorder.start();
                gravando = true;
                btnGravarOcorrencia.textContent = 'Parar gravação';
                btnGravarOcorrencia.classList.remove('bg-red-600', 'hover:bg-red-700');
                btnGravarOcorrencia.classList.add('bg-gray-800', 'hover:bg-gray-900');
                if (gravacaoStatus) {
                    gravacaoStatus.textContent = 'Gravando... clique em "Parar gravação" para finalizar.';
                    gravacaoStatus.classList.remove('hidden');
                }
            } catch (err) {
                alert('Não foi possível acessar o microfone.');
            }
        } else {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            if (mediaRecorder && mediaRecorder.stream) {
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
            }
            gravando = false;
            btnGravarOcorrencia.textContent = 'Gravar ocorrência';
            btnGravarOcorrencia.classList.remove('bg-gray-800', 'hover:bg-gray-900');
            btnGravarOcorrencia.classList.add('bg-red-600', 'hover:bg-red-700');
        }
    });
}

renderSelecionados();
</script>

