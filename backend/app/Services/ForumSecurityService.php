<?php
/**
 * Forum: sanitization, rate limit, permission checks.
 */
if (!class_exists('Logger')) {
    require_once __DIR__ . '/../Core/Logger.php';
}

class ForumSecurityService
{
    private static $replyCountKey = 'forum_replies_count';
    private static $topicCountKey = 'forum_topics_count';
    private static $windowSeconds = 60;
    private static $maxRepliesPerMinute = 5;
    private static $maxTopicsPerMinute = 3;

    /**
     * Map session tipo to forum role.
     */
    public static function userRole($tipo)
    {
        $map = ['aluno' => 'student', 'professor' => 'teacher', 'admin' => 'admin', 'admin_escola' => 'admin'];
        return $map[$tipo] ?? 'student';
    }

    /**
     * Sanitize HTML for display (strip tags, allow basic line breaks).
     */
    public static function sanitizeHtml($content)
    {
        $content = trim((string) $content);
        $content = strip_tags($content);
        $content = preg_replace('/\r\n|\r|\n/', "\n", $content);
        return $content;
    }

    /**
     * Check if user can mark best answer (teacher or admin only).
     */
    public static function canMarkBestAnswer($userRole)
    {
        return in_array($userRole, ['teacher', 'admin'], true);
    }

    /**
     * Check if user can moderate (delete topic/reply, resolve reports).
     */
    public static function canModerate($userRole)
    {
        return $userRole === 'admin';
    }

    /**
     * Rate limit: replies per minute (per user session).
     */
    public static function checkReplyRateLimit()
    {
        if (!isset($_SESSION[self::$replyCountKey])) {
            $_SESSION[self::$replyCountKey] = ['count' => 0, 'start' => time()];
        }
        $data = &$_SESSION[self::$replyCountKey];
        if (time() - $data['start'] > self::$windowSeconds) {
            $data = ['count' => 0, 'start' => time()];
        }
        $data['count']++;
        if ($data['count'] > self::$maxRepliesPerMinute) {
            return false;
        }
        return true;
    }

    /**
     * Rate limit: topics per minute.
     */
    public static function checkTopicRateLimit()
    {
        if (!isset($_SESSION[self::$topicCountKey])) {
            $_SESSION[self::$topicCountKey] = ['count' => 0, 'start' => time()];
        }
        $data = &$_SESSION[self::$topicCountKey];
        if (time() - $data['start'] > self::$windowSeconds) {
            $data = ['count' => 0, 'start' => time()];
        }
        $data['count']++;
        if ($data['count'] > self::$maxTopicsPerMinute) {
            return false;
        }
        return true;
    }
}
