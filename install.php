<?php
/**
 * Project: 流星MCS 安装程序
 * Version: v1.5
 */
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

if (file_exists('config.php')) {
    die("<h1 style='color:green;text-align:center;margin-top:50px'>✅ 系统已安装</h1><p style='text-align:center'>请删除此文件或 config.php 后重试。</p>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = $_POST['db'];
    try {
        $c = new PDO("mysql:host={$db['host']}", $db['user'], $db['pass']);
        $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $c->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` DEFAULT CHARSET utf8mb4");
        $c->exec("USE `{$db['name']}`");
        $c->exec("CREATE TABLE IF NOT EXISTS authme (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255) UNIQUE, realname VARCHAR(255), password VARCHAR(255), email VARCHAR(255), ip VARCHAR(45), lastlogin BIGINT, regdate BIGINT, reset_code VARCHAR(10), reset_time BIGINT)");
        $configData = [
            'db' => $db,
            'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
            'admin' => ['user'=>'admin', 'pass'=>$_POST['admin_pass']],
            'site' => ['title'=>'流星MCS玩家注册', 'ver'=>'v1.5']
        ];
        file_put_contents('config.php', "<?php\nreturn " . var_export($configData, true) . ";");
        header("Location: index.php?msg=installed"); exit;
    } catch (Exception $e) { $error = "安装失败: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>安装</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded shadow-lg w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6 text-blue-600">系统初始化 v1.5</h2>
        <?php if(isset($error)): ?><div class="bg-red-100 text-red-700 p-2 mb-4 text-sm rounded"><?= $error ?></div><?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="text" name="db[host]" value="127.0.0.1" class="w-full p-2 border rounded" placeholder="DB Host" required>
            <input type="text" name="db[name]" value="authme" class="w-full p-2 border rounded" placeholder="DB Name" required>
            <input type="text" name="db[user]" placeholder="DB User" class="w-full p-2 border rounded" required>
            <input type="password" name="db[pass]" placeholder="DB Password" class="w-full p-2 border rounded">
            <div class="pt-4 border-t"><input type="text" name="admin_pass" placeholder="设置后台密码" class="w-full p-2 border rounded" required></div>
            <button class="w-full bg-blue-600 text-white p-3 rounded font-bold hover:bg-blue-700">安装</button>
        </form>
    </div>
</body>
</html>
