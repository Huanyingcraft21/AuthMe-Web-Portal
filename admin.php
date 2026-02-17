<?php
/**
 * Project: 流星MCS 后台管理
 * Version: v1.5 (Brute-force Protected)
 */
define('IN_ADMIN', true);
if (!file_exists('index.php')) die("核心缺失");
require 'index.php';
if (!file_exists($configFile)) die("未安装");

// --- 防爆破模块 ---
$limitFile = 'login_limit.json';
function checkLock($f) {
    $ip = $_SERVER['REMOTE_ADDR']; $d = file_exists($f)?json_decode(file_get_contents($f),true):[];
    if (isset($d[$ip]) && $d[$ip]['c'] >= 3 && time()-$d[$ip]['t'] < 3600) return true;
    return false;
}
function logFail($f) {
    $ip = $_SERVER['REMOTE_ADDR']; $d = file_exists($f)?json_decode(file_get_contents($f),true):[];
    if(!isset($d[$ip])) $d[$ip]=['c'=>0,'t'=>time()];
    $d[$ip]['c']++; $d[$ip]['t']=time();
    file_put_contents($f, json_encode($d)); return $d[$ip]['c'];
}
function clearFail($f) {
    $ip = $_SERVER['REMOTE_ADDR']; $d = file_exists($f)?json_decode(file_get_contents($f),true):[];
    if(isset($d[$ip])) { unset($d[$ip]); file_put_contents($f, json_encode($d)); }
}

$action = $_GET['action'] ?? 'login';

// 锁定拦截
if (checkLock($limitFile) && $action === 'do_login') die("<h1 style='color:red;text-align:center;margin-top:50px'>🚫 IP已锁定</h1><p style='text-align:center'>错误次数过多，请1小时后再试。</p>");

if ($action === 'logout') { session_destroy(); header("Location: ?action=login"); exit; }
if ($action === 'do_login') {
    if ($_POST['user'] === $config['admin']['user'] && $_POST['pass'] === $config['admin']['pass']) {
        clearFail($limitFile); $_SESSION['is_admin'] = true; header("Location: ?action=dashboard");
    } else {
        $c = logFail($limitFile); header("Location: ?action=login&msg=err_auth&rem=".(3-$c));
    } exit;
}
if ($action !== 'login' && $action !== 'do_login' && !isset($_SESSION['is_admin'])) { header("Location: ?action=login"); exit; }

if ($action === 'do_save') {
    $n = $config;
    $n['site']['title']=$_POST['site_title'];
    $n['db']['host']=$_POST['dh']; $n['db']['name']=$_POST['dn']; $n['db']['user']=$_POST['du']; if($_POST['dp']) $n['db']['pass']=$_POST['dp'];
    $n['smtp']['host']=$_POST['sh']; $n['smtp']['port']=$_POST['sp']; $n['smtp']['user']=$_POST['su']; $n['smtp']['pass']=$_POST['spa']; $n['smtp']['from_name']=$_POST['sf'];
    if($_POST['ap']) { $n['admin']['user']=$_POST['au']; $n['admin']['pass']=$_POST['ap']; }
    header("Location: ?action=dashboard&tab=set&msg=".(saveConfig($n)?"save_ok":"save_fail")); exit;
}
if ($action === 'test_mail') { $s = new TinySMTP(); header("Location: ?action=dashboard&tab=set&msg=".($s->send($config['smtp']['user'],"Test","OK",$config['smtp'])?'mail_ok':'mail_fail')); exit; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>后台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{background:#f3f4f6} .input{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:0.3rem} .btn{width:100%;padding:0.6rem;background:#2563eb;color:#fff;border-radius:0.3rem;font-weight:bold}</style>
</head>
<body class="p-4">
    <?php if(isset($_GET['msg'])): ?><div class="fixed top-5 left-1/2 -translate-x-1/2 px-4 py-2 rounded shadow text-white text-sm font-bold bg-blue-500 z-50"><?= isset($_GET['rem'])?"密码错误! 剩余{$_GET['rem']}次":'操作完成' ?></div><?php endif; ?>

    <?php if ($action === 'login'): ?>
    <div class="max-w-sm mx-auto mt-20 bg-white p-8 rounded shadow text-center">
        <h2 class="text-xl font-bold mb-6">后台登录</h2>
        <form action="?action=do_login" method="POST" class="space-y-4">
            <input type="text" name="user" placeholder="账号" class="input">
            <input type="password" name="pass" placeholder="密码" class="input">
            <button class="btn">Login</button>
        </form>
        <div class="mt-4"><a href="index.php" class="text-gray-500 text-sm">返回首页</a></div>
    </div>
    
    <?php elseif ($action === 'dashboard'): $tab = $_GET['tab'] ?? 'usr'; ?>
    <div class="max-w-5xl mx-auto bg-white rounded shadow min-h-[600px] flex flex-col md:flex-row">
        <div class="w-full md:w-48 bg-gray-50 p-4 border-r">
            <h2 class="font-bold text-lg mb-6 text-gray-700">控制台</h2>
            <a href="?action=dashboard&tab=usr" class="block py-2 text-gray-600 font-bold <?= $tab=='usr'?'text-blue-600':'' ?>">👥 玩家</a>
            <a href="?action=dashboard&tab=set" class="block py-2 text-gray-600 font-bold <?= $tab=='set'?'text-blue-600':'' ?>">⚙️ 设置</a>
            <a href="?action=logout" class="block py-2 text-red-500 text-sm mt-4">退出</a>
            <div class="mt-8 text-xs text-gray-400 text-center">Ver 1.5</div>
        </div>
        <div class="flex-1 p-6 overflow-auto">
            <?php if ($tab === 'usr'): ?>
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100"><tr><th class="p-2">ID</th><th>用户</th><th>邮箱</th><th>IP</th></tr></thead>
                    <?php if($pdo): foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?>
                    <tr class="border-b"><td class="p-2"><?=$r['id']?></td><td class="font-bold"><?=htmlspecialchars($r['realname'])?></td><td class="text-gray-500"><?=htmlspecialchars($r['email'])?></td><td class="text-xs"><?=$r['ip']?></td></tr>
                    <?php endforeach; endif; ?>
                </table>
            <?php elseif ($tab === 'set'): ?>
                <form action="?action=do_save" method="POST" class="max-w-2xl space-y-3">
                    <h3 class="font-bold text-blue-600 border-b pb-1">基本</h3>
                    <input type="text" name="site_title" value="<?=$config['site']['title']?>" class="input" placeholder="网站标题">
                    <h3 class="font-bold text-blue-600 border-b pb-1 pt-4">数据库</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="dh" value="<?=$config['db']['host']?>" class="input"><input type="text" name="dn" value="<?=$config['db']['name']?>" class="input">
                        <input type="text" name="du" value="<?=$config['db']['user']?>" class="input"><input type="password" name="dp" placeholder="密码(留空不改)" class="input">
                    </div>
                    <h3 class="font-bold text-blue-600 border-b pb-1 pt-4 flex justify-between"><span>SMTP</span><a href="?action=test_mail" class="text-xs bg-gray-200 px-2 rounded">测试</a></h3>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="sh" value="<?=$config['smtp']['host']?>" class="input"><input type="text" name="sp" value="<?=$config['smtp']['port']?>" class="input">
                        <input type="text" name="su" value="<?=$config['smtp']['user']?>" class="input"><input type="password" name="spa" value="<?=$config['smtp']['pass']?>" class="input">
                        <input type="text" name="sf" value="<?=$config['smtp']['from_name']?>" class="input col-span-2">
                    </div>
                    <h3 class="font-bold text-blue-600 border-b pb-1 pt-4">管理员</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="au" value="<?=$config['admin']['user']?>" class="input"><input type="text" name="ap" placeholder="密码(留空不改)" class="input">
                    </div>
                    <button class="btn mt-4">保存</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
