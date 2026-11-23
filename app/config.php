<?php
/**
 * 站点与数据库配置。
 *
 * 敏感配置放在 config.local.php（已被 .gitignore 忽略）；
 * 新环境请复制 config.local.example.php 为 config.local.php 后填写。
 */
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    return require $local;
}

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'travel_share',
        'user'    => 'travel_app',
        'pass'    => '请填写数据库密码',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name'       => '旅行信息分享平台',
        'base_url'   => '',                       // 部署在域名根目录时留空
        'upload_dir' => dirname(__DIR__) . '/public/uploads',
        'upload_url' => '/uploads',
        'per_page'   => 9,
    ],
    'debug' => true,
];
