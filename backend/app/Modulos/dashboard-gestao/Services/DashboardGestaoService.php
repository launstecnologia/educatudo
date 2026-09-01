<?php

namespace App\Modulos\DashboardGestao\Services;

use App\Modulos\DashboardGestao\Widgets\AtencaoPedagogicaWidget;
use App\Modulos\DashboardGestao\Widgets\AvaliacoesWidget;
use App\Modulos\DashboardGestao\Widgets\CalendarioWidget;
use App\Modulos\DashboardGestao\Widgets\ConselhoWidget;
use App\Modulos\DashboardGestao\Widgets\DesempenhoWidget;
use App\Modulos\DashboardGestao\Widgets\DiariosWidget;
use App\Modulos\DashboardGestao\Widgets\EvolucaoWidget;
use App\Modulos\DashboardGestao\Widgets\FrequenciaHojeWidget;
use App\Modulos\DashboardGestao\Widgets\KpisWidget;
use App\Modulos\DashboardGestao\Widgets\MatriculasWidget;
use App\Modulos\DashboardGestao\Widgets\OcorrenciasWidget;
use App\Modulos\DashboardGestao\Widgets\PendenciasWidget;
use App\Modulos\DashboardGestao\Widgets\WidgetDashboard;
use Database;
use RedisCache;

require_once dirname(__DIR__, 3) . '/Core/RedisCache.php';
require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';

/**
 * Orquestra widgets do Dashboard. Não calcula regra acadêmica — só lê serviços oficiais.
 */
class DashboardGestaoService
{
    private const CACHE_TTL = 45;

    /** @var array<string, class-string<WidgetDashboard>> */
    private const WIDGETS = [
        'kpis' => KpisWidget::class,
        'pendencias' => PendenciasWidget::class,
        'frequencia_hoje' => FrequenciaHojeWidget::class,
        'desempenho' => DesempenhoWidget::class,
        'evolucao' => EvolucaoWidget::class,
        'atencao_pedagogica' => AtencaoPedagogicaWidget::class,
        'diarios' => DiariosWidget::class,
        'avaliacoes' => AvaliacoesWidget::class,
        'conselho' => ConselhoWidget::class,
        'ocorrencias' => OcorrenciasWidget::class,
        'calendario' => CalendarioWidget::class,
        'matriculas' => MatriculasWidget::class,
    ];

    /** @var array<string, string|null> widget => permissão admin (null = sempre) */
    private const PERMISSOES = [
        'kpis' => 'dashboard',
        'pendencias' => 'diario_classe',
        'frequencia_hoje' => 'diario_classe',
        'desempenho' => 'resultados_finais',
        'evolucao' => 'dashboard',
        'atencao_pedagogica' => 'diario_classe',
        'diarios' => 'diario_classe',
        'avaliacoes' => 'provas_online',
        'conselho' => 'conselho_classe',
        'ocorrencias' => 'ocorrencias',
        'calendario' => 'calendario_escolar',
        'matriculas' => 'alunos',
    ];

    private Database $db;
    private DashboardConsulta $consulta;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->consulta = new DashboardConsulta($this->db);
    }

    public function consulta(): DashboardConsulta
    {
        return $this->consulta;
    }

    /**
     * @return list<string>
     */
    public function chaves(): array
    {
        return array_keys(self::WIDGETS);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function montarFiltros(array $input, array $user = []): array
    {
        $filtro = DashboardFiltro::deInput($input, $this->db);
        return [
            'filtro' => [
                'ano_letivo_id' => $filtro->anoLetivoId,
                'ano' => $filtro->anoCivil,
                'bimestre' => $filtro->bimestre,
                'curso_id' => $filtro->cursoId,
                'serie_id' => $filtro->serieId,
                'turma_id' => $filtro->turmaId,
                'turno' => $filtro->turno,
            ],
            'anos' => DashboardFiltro::listarAnosLetivos($this->db),
            'cursos' => $this->consulta->cursos(),
            'series' => $this->consulta->series($filtro->cursoId > 0 ? $filtro->cursoId : null),
            'turmas' => $this->consulta->turmas($filtro),
            'turnos' => [
                'manha' => 'Manhã',
                'tarde' => 'Tarde',
                'noite' => 'Noite',
                'integral' => 'Integral',
            ],
            'widgets' => $this->widgetsVisiveis($user),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    public function widget(string $chave, array $input, array $user = []): array
    {
        if (!isset(self::WIDGETS[$chave])) {
            return ['ok' => false, 'erro' => 'Widget desconhecido'];
        }
        if (!$this->podeVer($chave, $user)) {
            return ['ok' => true, 'disponivel' => false, 'motivo' => 'Sem permissão para este bloco.'];
        }

        $filtro = DashboardFiltro::deInput($input, $this->db);
        $cacheKey = $this->cacheKey($chave, $filtro);
        $cached = RedisCache::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                $decoded['cache'] = true;
                return $decoded;
            }
        }

        try {
            $widget = $this->instanciar($chave);
            $payload = $widget->montar($filtro);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'disponivel' => false,
                'motivo' => 'Não foi possível carregar este bloco agora.',
                'cache' => false,
            ];
        }

        RedisCache::set($cacheKey, json_encode($payload, JSON_UNESCAPED_UNICODE), self::CACHE_TTL);
        $payload['cache'] = false;
        return $payload;
    }

    /**
     * @param array<string,mixed> $user
     * @return list<string>
     */
    public function widgetsVisiveis(array $user): array
    {
        $out = [];
        foreach (array_keys(self::WIDGETS) as $chave) {
            if ($chave === 'diarios') {
                continue;
            }
            if ($this->podeVer($chave, $user)) {
                $out[] = $chave;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $user
     */
    private function podeVer(string $chave, array $user): bool
    {
        $perm = self::PERMISSOES[$chave] ?? 'dashboard';
        if ($perm === null || $perm === 'dashboard') {
            return true;
        }
        $permissions = \AdminPermissionMatrix::effectivePermissionsForUser($this->db, $user);
        if (\AdminPermissionMatrix::can($permissions, $perm, 'visualizar')) {
            return true;
        }
        if ($chave === 'calendario' && \AdminPermissionMatrix::can($permissions, 'calendario_letivo', 'visualizar')) {
            return true;
        }
        if ($chave === 'frequencia_hoje' && \AdminPermissionMatrix::can($permissions, 'presenca', 'visualizar')) {
            return true;
        }
        if ($chave === 'pendencias' && \AdminPermissionMatrix::can($permissions, 'provas_online', 'visualizar')) {
            return true;
        }
        return false;
    }

    private function instanciar(string $chave): WidgetDashboard
    {
        $classe = self::WIDGETS[$chave];
        $precisaConsulta = in_array($chave, ['kpis', 'desempenho', 'evolucao', 'avaliacoes', 'calendario', 'matriculas'], true);
        if ($precisaConsulta) {
            return new $classe($this->consulta);
        }
        return new $classe();
    }

    private function cacheKey(string $chave, DashboardFiltro $filtro): string
    {
        $tenant = defined('TENANT_ID') ? (int) TENANT_ID : 0;
        return 'dash_gestao:' . $tenant . ':' . $chave . ':' . md5(json_encode([
            $filtro->anoLetivoId,
            $filtro->bimestre,
            $filtro->cursoId,
            $filtro->serieId,
            $filtro->turmaId,
            $filtro->turno,
            $filtro->hoje,
        ]));
    }
}
