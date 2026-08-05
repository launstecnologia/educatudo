<?php
/**
 * Componente: Banner de Notificação para Navbar
 */
$usuario = $usuario ?? null;
$tipoUsuario = $tipoUsuario ?? 'aluno';
$notificacoesNaoLidas = $notificacoesNaoLidas ?? 0;
$notificacoesRecentes = $notificacoesRecentes ?? [];

function normalizarUrlNotificacao($url) {
    if (empty($url)) {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    return URL . $url;
}

// Buscar notificações não lidas para o modal
$notificacoesModal = [];
if ($usuario && $notificacoesNaoLidas > 0) {
    require_once __DIR__ . '/../../../Models/Notifications/Notification.php';
    $notificacaoModel = new Notification();
    
    // Buscar apenas notificações não lidas
    $tipoDestinatario = $notificacaoModel->mapearTipoUsuario($tipoUsuario);
    
    $sql = "SELECT DISTINCT n.*, nd.lida, nd.lida_em, nd.visualizada_em,
                   CASE 
                       WHEN n.tipo_enviador = 'admin' THEN u.nome
                       WHEN n.tipo_enviador = 'professor' THEN p.nome
                   END as nome_enviador
            FROM notificacoes n
            LEFT JOIN notificacoes_destinatarios nd ON n.id = nd.notificacao_id
            LEFT JOIN usuarios u ON n.enviado_por = u.id AND n.tipo_enviador = 'admin'
            LEFT JOIN professores p ON n.enviado_por = p.id AND n.tipo_enviador = 'professor'
            WHERE n.ativo = 1 
            AND (n.data_expiracao IS NULL OR n.data_expiracao > NOW())
            AND nd.lida = 0
            AND (
                nd.tipo_destinatario = 'todos' OR
                (nd.tipo_destinatario = ? AND nd.destinatario_id = ?) OR
                (nd.tipo_destinatario = 'turma' AND nd.turma_id IN (
                    SELECT turma_id FROM alunos WHERE id = ?
                ))
            )
            ORDER BY n.created_at DESC
            LIMIT 10";
    
    $params = [$tipoDestinatario, $usuario['id'], $usuario['id']];
    
    // Usar Database diretamente em vez de propriedade privada
    require_once __DIR__ . '/../../../Core/Database.php';
    $db = Database::getInstance();
    $notificacoesModal = $db->fetchAll($sql, $params);
}
?>

<!-- Banner de Notificação -->
<div id="notification-banner" class="bg-blue-600 text-white px-4 py-2 text-center relative" style="display: none;">
    <div class="flex items-center justify-center">
        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
        </svg>
        <span id="notification-message">Você tem novas notificações!</span>
        <button onclick="abrirModalNotificacoes()" class="ml-4 bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded text-sm">
            Ver Notificações
        </button>
        <button onclick="atualizarSistema()" class="ml-2 bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded text-sm">
            Atualizar Sistema
        </button>
        <button onclick="fecharBanner()" class="ml-2 text-blue-200 hover:text-white">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        </button>
    </div>
</div>

<!-- Modal de Notificações -->
<div id="notification-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
    <!-- Backdrop com transição suave -->
    <div id="modal-backdrop" class="fixed inset-0 bg-gray-500/75 transition-opacity duration-300 ease-out opacity-0" onclick="fecharModalNotificacoes()"></div>
    
    <!-- Container do Modal -->
    <div class="relative transform transition-all duration-300 ease-out opacity-0 scale-95 translate-y-4 sm:translate-y-0 sm:scale-95">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden border border-gray-200">
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="mx-auto flex size-10 shrink-0 items-center justify-center rounded-full bg-white/20 mr-3">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold">Novas Notificações</h2>
                            <p class="text-purple-100 text-sm">Você tem <span id="modal-notification-count">0</span> notificação(ões) não lida(s)</p>
                        </div>
                    </div>
                    <button onclick="fecharModalNotificacoes()" class="text-white hover:text-purple-200 transition-colors p-1 rounded-full hover:bg-white/10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Conteúdo do Modal -->
            <div class="bg-white px-6 py-4">
                <div id="modal-notifications-content" class="space-y-4 max-h-[60vh] overflow-y-auto">
                    <?php if (!empty($notificacoesModal)): ?>
                        <?php foreach ($notificacoesModal as $notif): ?>
                            <div class="bg-gray-50 rounded-lg p-4 border-l-4 <?= $notif['lida'] ? 'border-gray-300' : 'border-blue-500' ?> hover:bg-gray-100 transition-colors cursor-pointer border border-gray-200" onclick="visualizarNotificacao(<?= $notif['id'] ?>)">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($notif['titulo']) ?></h3>
                                            <?php if (!$notif['lida']): ?>
                                                <span class="ml-2 w-2 h-2 bg-blue-500 rounded-full"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-gray-600 text-sm mb-2">
                                            <?php 
                                            // Verificar se tem tipos de conteúdo
                                            $tiposConteudo = json_decode($notif['tipos_conteudo'] ?? '[]', true);
                                            if (!is_array($tiposConteudo)) {
                                                // Se não for JSON válido, tratar como string separada por vírgula
                                                $tiposConteudo = explode(',', $notif['tipos_conteudo'] ?? '');
                                                $tiposConteudo = array_map('trim', $tiposConteudo);
                                                $tiposConteudo = array_filter($tiposConteudo); // Remove valores vazios
                                            }
                                            
                                            // Texto completo
                                            if (in_array('texto', $tiposConteudo) || empty($tiposConteudo)) {
                                                echo '<div class="mb-3">';
                                                echo $notif['conteudo']; // Exibir HTML completo
                                                echo '</div>';
                                            }
                                            
                                            // Imagem completa
                                            if (in_array('imagem', $tiposConteudo) && !empty($notif['arquivo_url'])) {
                                                $imgUrl = normalizarUrlNotificacao($notif['arquivo_url']);
                                                echo '<div class="mb-3">';
                                                echo '<img src="' . htmlspecialchars($imgUrl) . '" class="max-w-full h-auto rounded-lg shadow-lg" alt="Imagem da notificação">';
                                                echo '</div>';
                                            }
                                            
                                            // Vídeo completo
                                            if (in_array('video', $tiposConteudo) && !empty($notif['video_url'])) {
                                                echo '<div class="mb-3">';
                                                echo '<div class="bg-gray-100 rounded-lg p-4">';
                                                echo '<h4 class="font-semibold text-gray-900 mb-2">Vídeo</h4>';
                                                echo '<a href="' . htmlspecialchars($notif['video_url']) . '" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">';
                                                echo '<svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">';
                                                echo '<path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>';
                                                echo '</svg>';
                                                echo 'Assistir Vídeo';
                                                echo '</a>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="flex items-center justify-between text-xs text-gray-500">
                                            <span>Por <?= htmlspecialchars($notif['nome_enviador'] ?? 'Sistema') ?></span>
                                            <span><?= date('d/m H:i', strtotime($notif['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <p class="text-gray-500">Nenhuma notificação encontrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer do Modal -->
            <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-t border-gray-200">
                <div class="flex space-x-3">
                    <?php 
                    // Verificar se alguma notificação é de atualização
                    $temAtualizacao = false;
                    if (!empty($notificacoesModal)) {
                        foreach ($notificacoesModal as $notif) {
                            if ($notif['is_update'] == 1) {
                                $temAtualizacao = true;
                                break;
                            }
                        }
                    }
                    ?>
                    <?php if ($temAtualizacao): ?>
                    <button onclick="atualizarSistema()" class="inline-flex justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                        Atualizar Sistema
                    </button>
                    <?php endif; ?>
                </div>
                <div class="flex space-x-3">
                    <button onclick="fecharModalNotificacoes()" class="inline-flex justify-center rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ícone de Notificação no Navbar -->
<div class="relative">
    <button onclick="toggleNotificationDropdown()" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        
        <!-- Badge de notificações não lidas -->
        <?php if ($notificacoesNaoLidas > 0): ?>
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                <?= $notificacoesNaoLidas > 99 ? '99+' : $notificacoesNaoLidas ?>
            </span>
        <?php endif; ?>
    </button>

    <!-- Dropdown de Notificações -->
    <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border z-50" style="display: none;">
        <div class="p-4 border-b">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Notificações</h3>
                <a href="<?= URL ?>/notifications" class="text-blue-600 hover:text-blue-800 text-sm">Ver todas</a>
            </div>
        </div>
        
        <div class="max-h-96 overflow-y-auto">
            <?php if (empty($notificacoesModal)): ?>
                <div class="p-4 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p>Nenhuma notificação</p>
                </div>
            <?php else: ?>
                <?php foreach ($notificacoesModal as $notificacao): ?>
                    <div class="p-4 border-b hover:bg-gray-50 cursor-pointer" onclick="visualizarNotificacao(<?= $notificacao['id'] ?>)">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <?php if ($notificacao['tipo_conteudo'] === 'video'): ?>
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($notificacao['titulo']) ?></p>
                                        <div class="text-sm text-gray-500 mt-1">
                                            <?php 
                                            // Verificar se tem tipos de conteúdo
                                            $tiposConteudo = json_decode($notificacao['tipos_conteudo'] ?? '[]', true);
                                            if (!is_array($tiposConteudo)) {
                                                $tiposConteudo = [];
                                            }
                                            
                                            if (in_array('texto', $tiposConteudo) || empty($tiposConteudo)) {
                                                // Exibir texto
                                                $conteudoTexto = strip_tags($notificacao['conteudo']);
                                                echo htmlspecialchars(substr($conteudoTexto, 0, 80));
                                                if (strlen($conteudoTexto) > 80) echo '...';
                                            }
                                            
                                            if (in_array('imagem', $tiposConteudo) && !empty($notificacao['arquivo_url'])) {
                                                $imgUrl = normalizarUrlNotificacao($notificacao['arquivo_url']);
                                                echo '<br><img src="' . htmlspecialchars($imgUrl) . '" class="w-12 h-12 object-cover rounded mt-1" alt="Imagem">';
                                            }
                                            
                                            if (in_array('video', $tiposConteudo) && !empty($notificacao['video_url'])) {
                                                echo '<br><span class="text-blue-600 text-xs">📹 Vídeo disponível</span>';
                                            }
                                            ?>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1">Por <?= htmlspecialchars($notificacao['nome_enviador']) ?></p>
                                    </div>
                                    <?php if (!$notificacao['lida']): ?>
                                        <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-2 flex justify-between items-center">
                                    <span class="text-xs text-gray-400">
                                        <?= date('d/m H:i', strtotime($notificacao['created_at'])) ?>
                                    </span>
                                    <?php if (!$notificacao['lida']): ?>
                                        <button onclick="event.stopPropagation(); marcarComoLida(<?= $notificacao['id'] ?>)" 
                                                class="text-xs text-blue-600 hover:text-blue-800">
                                            Marcar como lida
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t bg-gray-50">
            <div class="flex justify-between">
                <button onclick="atualizarSistema()" class="text-sm text-blue-600 hover:text-blue-800">
                    Atualizar Sistema
                </button>
                <button onclick="marcarTodasComoLidas()" class="text-sm text-blue-600 hover:text-blue-800">
                    Marcar todas como lidas
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let notificationDropdownOpen = false;

// Verificar notificações não lidas ao carregar a página
// Desativado por performance: não iniciar polling/SSE automaticamente
document.addEventListener('DOMContentLoaded', function() {
    // Notificações agora só atualizam quando o usuário abre o modal
    const totalNaoLidas = <?= (int)$notificacoesNaoLidas ?>;
    const currentPage = '<?= htmlspecialchars($current_page ?? '') ?>';
    const popupShownKey = 'notificacoes_popup_exibido';

    if (totalNaoLidas > 0) {
        if (currentPage === 'dashboard') {
            mostrarModalNotificacoes(totalNaoLidas);
        } else if (!sessionStorage.getItem(popupShownKey)) {
            mostrarModalNotificacoes(totalNaoLidas);
            sessionStorage.setItem(popupShownKey, '1');
        }
    }
});

function mostrarModalAutomatico() {
    const totalNaoLidas = <?= $notificacoesNaoLidas ?>;
    if (totalNaoLidas > 0) {
        mostrarModalNotificacoes(totalNaoLidas);
    }
}

function verificarNotificacoesNaoLidas() {
    fetch('<?= URL ?>/notifications/api/nao-lidas')
        .then(response => response.json())
        .then(data => {
            atualizarEstadoNotificacoes(data.total || 0);
        })
        .catch(error => console.error('Erro ao verificar notificações:', error));
}

function atualizarEstadoNotificacoes(total) {
    if (total > 0) {
        mostrarBanner();
        atualizarBadge(total);
    } else {
        esconderBanner();
        atualizarBadge(0);
    }
}

function iniciarStreamNotificacoes() {
    // Desativado por performance
}

function mostrarBanner() {
    const banner = document.getElementById('notification-banner');
    if (banner) {
        banner.style.display = 'block';
    }
}

function esconderBanner() {
    const banner = document.getElementById('notification-banner');
    if (banner) {
        banner.style.display = 'none';
    }
}

function fecharBanner() {
    esconderBanner();
}

function atualizarBadge(total) {
    const badge = document.querySelector('.absolute.-top-1.-right-1');
    if (badge) {
        if (total > 0) {
            badge.textContent = total > 99 ? '99+' : total;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Funções do Modal
function mostrarModalNotificacoes(total) {
    const modal = document.getElementById('notification-modal');
    const backdrop = document.getElementById('modal-backdrop');
    const countElement = document.getElementById('modal-notification-count');
    
    if (modal && backdrop && countElement) {
        countElement.textContent = total;
        modal.style.display = 'flex';
        
        // Animar backdrop
        setTimeout(() => {
            backdrop.style.opacity = '1';
        }, 10);
        
        // Animar modal
        setTimeout(() => {
            const modalPanel = modal.querySelector('.relative.transform');
            modalPanel.style.opacity = '1';
            modalPanel.style.transform = 'scale(1) translateY(0)';
        }, 50);
    }
}

function abrirModalNotificacoes() {
    const totalNaoLidas = <?= $notificacoesNaoLidas ?>;
    mostrarModalNotificacoes(totalNaoLidas);
}

function fecharModalNotificacoes() {
    const modal = document.getElementById('notification-modal');
    const backdrop = document.getElementById('modal-backdrop');
    
    if (modal && backdrop) {
        // Marcar todas como lidas automaticamente ao fechar
        marcarTodasComoLidas();
        
        // Animar saída
        const modalPanel = modal.querySelector('.relative.transform');
        modalPanel.style.opacity = '0';
        modalPanel.style.transform = 'scale(0.95) translateY(4px)';
        
        backdrop.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
            // Reset para próxima abertura
            modalPanel.style.opacity = '0';
            modalPanel.style.transform = 'scale(0.95) translateY(4px)';
            backdrop.style.opacity = '0';
        }, 300);
    }
}

function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notification-dropdown');
    if (dropdown) {
        notificationDropdownOpen = !notificationDropdownOpen;
        dropdown.style.display = notificationDropdownOpen ? 'block' : 'none';
        
        if (notificationDropdownOpen) {
            carregarNotificacoesRecentes();
        }
    }
}

function carregarNotificacoesRecentes() {
    fetch('<?= URL ?>/notifications/api/recentes')
        .then(response => response.json())
        .then(data => {
            // Atualizar dropdown com notificações recentes
            console.log('Notificações recentes:', data.notificacoes);
        })
        .catch(error => console.error('Erro ao carregar notificações:', error));
}

function visualizarNotificacao(id) {
    window.location.href = '<?= URL ?>/notifications/' + id;
}

function marcarComoLida(id) {
    console.log('Marcando notificação como lida:', id);
    
    fetch('<?= URL ?>/notifications/api/marcar-lida', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notificacao_id=' + id
    })
    .then(response => {
        console.log('Resposta da API:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text(); // Primeiro como texto para debug
    })
    .then(text => {
        console.log('Resposta texto:', text);
        try {
            const data = JSON.parse(text);
            console.log('Dados parseados:', data);
            if (data.success) {
                console.log('Sucesso ao marcar como lida');
                verificarNotificacoesNaoLidas();
                carregarNotificacoesRecentes();
            } else {
                console.error('Erro ao marcar como lida:', data.message);
            }
        } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e);
            console.error('Texto recebido:', text);
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
    });
}

function marcarTodasComoLidas() {
    console.log('Marcando todas as notificações como lidas...');
    
    fetch('<?= URL ?>/notifications/api/marcar-todas-lidas', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => {
        console.log('Resposta marcar todas:', response.status);
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text(); // Primeiro como texto para debug
    })
    .then(text => {
        console.log('Resposta texto:', text);
        try {
            const data = JSON.parse(text);
            console.log('Dados parseados:', data);
            if (data.success) {
                console.log('Sucesso ao marcar todas como lidas');
                verificarNotificacoesNaoLidas();
                carregarNotificacoesRecentes();
            } else {
                console.error('Erro ao marcar todas como lidas:', data.message);
            }
        } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e);
            console.error('Texto recebido:', text);
        }
    })
    .catch(error => {
        console.error('Erro na requisição marcar todas:', error);
    });
}

function atualizarSistema() {
    window.location.href = '<?= URL ?>/notifications/atualizar';
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notification-dropdown');
    const button = event.target.closest('button');
    
    if (dropdown && notificationDropdownOpen && !dropdown.contains(event.target) && !button) {
        notificationDropdownOpen = false;
        dropdown.style.display = 'none';
    }
});

// Fechar modal ao clicar fora
document.addEventListener('click', function(event) {
    const modal = document.getElementById('notification-modal');
    if (modal && modal.style.display === 'flex') {
        // Verificar se o clique foi no backdrop (fora do conteúdo do modal)
        if (event.target === modal) {
            marcarTodasComoLidas(); // Marcar todas como lidas ao fechar
            fecharModalNotificacoes();
        }
    }
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('notification-modal');
        if (modal && modal.style.display === 'flex') {
            marcarTodasComoLidas(); // Marcar todas como lidas ao fechar
            fecharModalNotificacoes();
        }
    }
});
</script>
