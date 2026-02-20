<?php
/**
 * Project: Meteor Nexus 配置文件蓝图
 * Version: 2.1.4
 */
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'authme', 'user' => 'root', 'pass' => ''],
    'smtp' => ['host' => 'smtp.qq.com', 'port' => 465, 'user' => '', 'pass' => '', 'secure' => 'ssl', 'from_name' => '流星网'],
    'admin' => ['user' => 'admin', 'pass' => 'password123', 'email' => ''],
    'site' => ['title' => 'Meteor Nexus', 'ver' => '2.1.1', 'bg' => ''],
    'display' => ['ip' => '', 'port' => '25565'], 
    'servers' => [['name' => '默认节点', 'ip' => '127.0.0.1', 'port' => 25565, 'api_port' => 8080, 'api_key' => '']],
    'rewards' => ['reg_cmd' => '', 'daily_cmd' => '', 'sign_in_servers' => [0]],
    'modules' => ['official' => 1, 'auth' => 1],
    'route' => [
        'default' => 'official', 
        'domain_official' => '', 
        'domain_auth' => '',
        'official_type' => 'local', 
        'official_url' => ''
    ]
];
