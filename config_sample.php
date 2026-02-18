<?php
// v1.7 Config Structure (Revised)
return [
    'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
    'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
    'admin' => ['user'=>'admin', 'pass'=>'password123', 'email'=>''],
    'site' => ['title'=>'流星MCS', 'ver'=>'1.7', 'bg'=>''],
    
    // 服务器列表 (ID 0, 1, 2...)
    'servers' => [
        ['name'=>'生存一区', 'ip'=>'127.0.0.1', 'port'=>'25565', 'rcon_port'=>'25575', 'rcon_pass'=>'123'],
        ['name'=>'空岛二区', 'ip'=>'127.0.0.1', 'port'=>'25566', 'rcon_port'=>'25576', 'rcon_pass'=>'123']
    ],
    
    // 奖励配置
    'rewards' => [
        'reg_cmd' => '', // 注册奖励
        'daily_cmd' => '', // 签到指令 (例如 mg give %player% points 10)
        'sign_in_servers' => [0, 1] // [重点] 签到时，向ID为0和1的服务器发送指令
    ]
];
