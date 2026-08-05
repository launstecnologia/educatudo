<?php
/**
 * Front controller — único ponto de entrada HTTP (docroot do Nginx/Apache).
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

if (!defined('ENV_FILE_PATH')) {
    define('ENV_FILE_PATH', BASE_PATH . '/.env');
}

require BASE_PATH . '/bootstrap/app.php';
