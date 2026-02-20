<?php
/**
 * Project: 流星MCS 配置文件蓝图
 * Note: 此文件用于后台在线更新时，自动补全缺失的新版本配置节点。
 * Version: v1.9 (MetorCore API Edition)
 */
return [
    'db' => [
        'host' => '127.0.0.1', 
        'name' => 'authme', 
        'user' => 'root', 
        'pass' => ''
    ],
    'smtp' => [
        'host' => 'smtp.qq.com', 
        'port' => 465, 
        'user' => '', 
        'pass' => '', 
        'secure' => 'ssl', 
        'from_name' => '流星MCS'
    ],
    'admin' => [
        'user' => 'admin', 
        'pass' => 'password123', 
        'email' => ''
    ],
    'site' => [
        'title' => '流星MCS', 
        'ver' => '1.9', 
        'bg' => ''
    ],
    'display' => [
        'ip' => '', 
        'port' => '25565'
    ], 
    'servers' => [
        [
            'name' => '默认服务器', 
            'ip' => '127.0.0.1', 
            'port' => 25565, 
            'api_port' => 8080, 
            'api_key' => ''
        ]
    ],
    'rewards' => [
        'reg_cmd' => '', 
        'daily_cmd' => '',
        'sign_in_servers' => [0]
    ],
    // 兼容单服模式的备用选项
    'server' => [
        'ip' => '', 
        'port' => '25565'
    ],
    'api' => [
        'host' => '127.0.0.1', 
        'port' => 8080, 
        'key' => ''
    ]
];
