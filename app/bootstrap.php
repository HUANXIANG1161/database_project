<?php
/**
 * 应用入口引导：配置、时区、Session、公共函数、数据库、登录态。
 * 所有页面第一行 require 本文件。
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['config'] = require __DIR__ . '/config.php';

date_default_timezone_set('Asia/Shanghai');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Windows 下 PHP 默认把 session 写到用户 Temp 目录；本机用不同权限启动 PHP 时
    // 该目录可能不可写，导致 session_start() 报 Permission denied。统一放到项目内。
    $sessionDir = APP_ROOT . '/storage/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    if (is_dir($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (config('debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
