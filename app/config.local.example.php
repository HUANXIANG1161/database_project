<?php
/**
 * 本地开发配置示例。
 * 复制为 config.local.php 后填写自己的数据库密码。
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'travel_share',
        'user'    => 'travel_app',
        'pass'    => '你的应用账号密码',
        'charset' => 'utf8mb4',
    ],
    'site' => [
        'name'       => '旅行信息分享平台',
        'base_url'   => '',
        'upload_dir' => dirname(__DIR__) . '/public/uploads',
        'upload_url' => '/uploads',
        'per_page'   => 9,
    ],
    'debug' => true,
];
