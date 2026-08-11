<?php
$title = $title ?? 'Tipos de Curso e Cursos - EducaTudo';
$user = $user ?? null;
$current_page = $current_page ?? '';
$csrf_token = $csrf_token ?? '';
$schema_ready = $schema_ready ?? false;
$tipos_curso = $tipos_curso ?? [];
$cursos = $cursos ?? [];
$status = (string)($status ?? '');
$message = (string)($message ?? '');
?>

<div class="min-h-screen bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50">
    <div class="bg-white/80 backdrop-blur-sm border-b border-purple-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Tipos de Curso e Cursos</h1>
                    <p class="mt-2 text-gray-600">Cadastre os tipos e cursos para usar no registro de turmas</p>
                </div>
                <div>
                    <a href="<?= URL ?>/admin/turmas"
                       class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        Voltar para Turmas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <?php if ($status !== '' && $message !== ''): ?>
            <div class="px-4 py-3 rounded-lg border <?= $status === 'success' ? 'bg-green-50 border-green-300 text-green-800' : 'bg-red-50 border-red-300 text-red-800' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$schema_ready): ?>
            <div class="px-4 py-3 rounded-lg border bg-yellow-50 border-yellow-300 text-yellow-800">
                Estrutura de cursos ainda não disponível neste banco. Execute a migration `006_tipos_curso_e_cursos.sql`.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div id="tipos" class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Cadastrar Tipo de Curso</h3>
                    </div>
                    <form method="POST" action="<?= URL ?>/admin/cursos/tipos" class="p-6 space-y-4">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div>
                            <label for="tipo_nome" class="block text-sm font-semibold text-gray-700 mb-2">Nome</label>
                            <input id="tipo_nome" type="text" name="nome" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                   placeholder="Ex: Ensino Médio">
                        </div>
                        <div>
                            <label for="tipo_ordem" class="block text-sm font-semibold text-gray-700 mb-2">Ordem</label>
                            <input id="tipo_ordem" type="number" name="ordem" value="0"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <label class="flex items-center">
                            <input type="checkbox" name="ativo" value="1" checked class="h-4 w-4 text-purple-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Ativo</span>
                        </label>
                        <button type="submit"
                                class="btn-primary-custom px-6 py-3 rounded-lg transition-colors hover:opacity-90">
                            Cadastrar Tipo
                        </button>
                    </form>
                </div>

                <div id="cursos" class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Cadastrar Curso</h3>
                    </div>
                    <form method="POST" action="<?= URL ?>/admin/cursos" class="p-6 space-y-4">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div>
                            <label for="curso_tipo" class="block text-sm font-semibold text-gray-700 mb-2">Tipo de Curso</label>
                            <select id="curso_tipo" name="tipo_curso_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                <option value="">Selecione</option>
                                <?php foreach ($tipos_curso as $tipo): ?>
                                    <option value="<?= (int)$tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="curso_nome" class="block text-sm font-semibold text-gray-700 mb-2">Nome do Curso</label>
                            <input id="curso_nome" type="text" name="nome" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                                   placeholder="Ex: 1º Ano, 2º Ano, Pré-vestibular">
                        </div>
                        <div>
                            <label for="curso_ordem" class="block text-sm font-semibold text-gray-700 mb-2">Ordem</label>
                            <input id="curso_ordem" type="number" name="ordem" value="0"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        </div>
                        <label class="flex items-center">
                            <input type="checkbox" name="ativo" value="1" checked class="h-4 w-4 text-purple-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Ativo</span>
                        </label>
                        <button type="submit"
                                class="btn-primary-custom px-6 py-3 rounded-lg transition-colors hover:opacity-90">
                            Cadastrar Curso
                        </button>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Tipos de Curso Cadastrados</h3>
                    </div>
                    <div class="p-6 overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 border-b">
                                    <th class="py-2 pr-2">Nome</th>
                                    <th class="py-2 pr-2">Ordem</th>
                                    <th class="py-2 pr-2">Cursos</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tipos_curso)): ?>
                                    <tr><td colspan="4" class="py-3 text-gray-500">Nenhum tipo cadastrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tipos_curso as $tipo): ?>
                                        <tr class="border-b last:border-b-0">
                                            <td class="py-2 pr-2 font-medium text-gray-800"><?= htmlspecialchars($tipo['nome']) ?></td>
                                            <td class="py-2 pr-2 text-gray-700"><?= (int)$tipo['ordem'] ?></td>
                                            <td class="py-2 pr-2 text-gray-700"><?= (int)$tipo['total_cursos'] ?></td>
                                            <td class="py-2">
                                                <span class="px-2 py-1 rounded text-xs <?= (int)$tipo['ativo'] === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                                    <?= (int)$tipo['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Cursos Cadastrados</h3>
                    </div>
                    <div class="p-6 overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-600 border-b">
                                    <th class="py-2 pr-2">Tipo</th>
                                    <th class="py-2 pr-2">Curso</th>
                                    <th class="py-2 pr-2">Ordem</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr><td colspan="4" class="py-3 text-gray-500">Nenhum curso cadastrado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cursos as $curso): ?>
                                        <tr class="border-b last:border-b-0">
                                            <td class="py-2 pr-2 text-gray-700"><?= htmlspecialchars($curso['tipo_nome']) ?></td>
                                            <td class="py-2 pr-2 font-medium text-gray-800"><?= htmlspecialchars($curso['nome']) ?></td>
                                            <td class="py-2 pr-2 text-gray-700"><?= (int)$curso['ordem'] ?></td>
                                            <td class="py-2">
                                                <span class="px-2 py-1 rounded text-xs <?= (int)$curso['ativo'] === 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                                    <?= (int)$curso['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
