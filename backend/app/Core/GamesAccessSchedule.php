<?php

require_once __DIR__ . '/LayoutHelper.php';

/**
 * Horário de acesso ao Games (aluno): fim de semana liberado;
 * segunda a sexta apenas das 17h até 7h do dia seguinte (madrugada após dia letivo).
 * Segunda-feira antes das 7h: fechado (não há noite de domingo contando como dia letivo).
 */
final class GamesAccessSchedule
{
    private const DEFAULT_TZ = 'America/Sao_Paulo';

    public static function isAlwaysUnrestrictedEnabled(): bool
    {
        // Padrão 1: menu Games clicável a qualquer hora; escolas que quiserem o calendário (17h–7h / fds)
        // gravam games_horario_sempre_liberado = 0 em config_layout.
        return LayoutHelper::get('games_horario_sempre_liberado', '1') === '1';
    }

    public static function timezone(): string
    {
        $tz = trim((string) LayoutHelper::get('games_timezone', ''));
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return self::DEFAULT_TZ;
    }

    public static function studentBlockedMessage(): string
    {
        return 'Jogos liberados de segunda a sexta das 17h às 7h (horário do dia seguinte). Fins de semana liberados o dia todo.';
    }

    public static function isAccessAllowedNow(?DateTimeInterface $now = null): bool
    {
        if (self::isAlwaysUnrestrictedEnabled()) {
            return true;
        }

        $tz = new DateTimeZone(self::timezone());
        $d = $now instanceof DateTimeInterface
            ? (new DateTimeImmutable('@' . $now->getTimestamp()))->setTimezone($tz)
            : new DateTimeImmutable('now', $tz);

        $dow = (int) $d->format('N');
        $h = (int) $d->format('G');
        $i = (int) $d->format('i');
        $minutes = $h * 60 + $i;

        if ($dow >= 6) {
            return true;
        }

        if ($dow === 1 && $minutes < 7 * 60) {
            return false;
        }

        if ($dow >= 2 && $dow <= 5 && $minutes < 7 * 60) {
            return true;
        }

        if ($minutes >= 17 * 60) {
            return true;
        }

        return false;
    }

    public static function linkLooksLikeGames(array $link): bool
    {
        $id = strtolower(trim((string) ($link['id'] ?? '')));
        if ($id === 'games') {
            return true;
        }

        $host = strtolower((string) parse_url(trim((string) ($link['url'] ?? '')), PHP_URL_HOST));

        return $host !== '' && strpos($host, 'games') !== false;
    }

    public static function shouldBlockStudentGamesLink(array $link): bool
    {
        if (self::isAlwaysUnrestrictedEnabled()) {
            return false;
        }

        return self::linkLooksLikeGames($link) && !self::isAccessAllowedNow();
    }
}
