<?php
return [
    'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
    'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
    'admin' => ['user'=>'admin', 'pass'=>'password123', 'email'=>''],
    'site' => [
        'title' => '流星MCS', 
        'ver'   => '1.8', // 这里现在支持任意格式，如 '1.8.1' 或 '2.0-beta'
        'bg'    => 'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920'
    ],
    'display' => ['ip'=>'127.0.0.1', 'port'=>'25565'], 
    'servers' => [
        ['name'=>'默认服务器', 'ip'=>'127.0.0.1', 'port'=>'25565', 'rcon_port'=>'25575', 'rcon_pass'=>'']
    ],
    'rewards' => [
        'reg_cmd' => '', 
        'daily_cmd' => '', 
        'sign_in_servers' => [0]
    ]
];
