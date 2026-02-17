<?php
/**
 * Project: 流星MCS Lite (Single File)
 * Version: v0.150
 * Note: 极速部署，包含安装/前台/后台/防爆破
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
error_reporting(0);

$configFile = 'config.php';
$limitFile = 'login_limit.json';

// --- 配置加载与兼容 ---
$defaultConfig = [
    'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
    'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
    'admin' => ['user'=>'admin', 'pass'=>'password123'],
    'site' => ['title'=>'流星MCS Lite', 'ver'=>'v0.150']
];
$config = file_exists($configFile) ? array_replace_recursive($defaultConfig, include($configFile)) : null;

// --- 核心库 ---
function saveConfig($c) { global $configFile; return file_put_contents($configFile, "<?php\nreturn " . var_export($c, true) . ";"); }
function hashAuthMe($p) { $s = bin2hex(random_bytes(8)); return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s); }
class TinySMTP {
    private $sock;
    public function send($to, $sub, $body, $conf) {
        $host = ($conf['secure'] == 'ssl' ? 'ssl://' : '') . $conf['host'];
        $this->sock = fsockopen($host, $conf['port']); if (!$this->sock) return false;
        $this->cmd(NULL); $this->cmd("EHLO " . $conf['host']); $this->cmd("AUTH LOGIN");
        $this->cmd(base64_encode($conf['user'])); $this->cmd(base64_encode($conf['pass']));
        $this->cmd("MAIL FROM: <{$conf['user']}>"); $this->cmd("RCPT TO: <$to>"); $this->cmd("DATA");
        fwrite($this->sock, "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?".base64_encode($conf['from_name'])."?= <{$conf['user']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?".base64_encode($sub)."?=\r\n\r\n$body\r\n.\r\n");
        $res = $this->get_lines(); $this->cmd("QUIT"); fclose($this->sock); return strpos($res, "250") !== false;
    }
    private function cmd($c) { if($c) fwrite($this->sock, $c."\r\n"); return $this->get_lines(); }
    private function get_lines() { $d=""; while($s=fgets($this->sock,515)){$d.=$s; if(substr($s,3,1)==" ")break;} return $d; }
}
// 防爆破
function checkLock($f) { $ip=$_SERVER['REMOTE_ADDR'];$d=file_exists($f)?json_decode(file_get_contents($f),true):[]; return (isset($d[$ip])&&$d[$ip]['c']>=3&&time()-$d[$ip]['t']<3600); }
function logFail($f) { $ip=$_SERVER['REMOTE_ADDR'];$d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(!isset($d[$ip]))$d[$ip]=['c'=>0,'t'=>time()]; $d[$ip]['c']++;$d[$ip]['t']=time(); file_put_contents($f,json_encode($d)); return $d[$ip]['c']; }
function clearFail($f) { $ip=$_SERVER['REMOTE_ADDR'];$d=file_exists($f)?json_decode(file_get_contents($f),true):[]; if(isset($d[$ip])){unset($d[$ip]);file_put_contents($f,json_encode($d));} }

// --- DB 连接 ---
$pdo = null;
if ($config) {
    try { $dsn="mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4"; $pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass']); } catch(Exception $e){}
}

$action = $_GET['action'] ?? 'home';
if (!$config && $action !== 'do_install') $action = 'install';

// --- 逻辑处理 ---
if ($action === 'do_reg') {
    if ($_POST['captcha'] != $_SESSION['captcha']) header("Location: ?msg=err");
    else {
        $u=strtolower(trim($_POST['username']));
        if($pdo->query("SELECT id FROM authme WHERE username='$u'")->fetch()) header("Location: ?msg=exists");
        else {
            $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u,$_POST['username'],hashAuthMe($_POST['password']),$_POST['email'],$_SERVER['REMOTE_ADDR'],time()*1000,time()*1000]);
            header("Location: ?msg=ok");
        }
    } exit;
}
if ($action === 'do_admin') {
    if(checkLock($limitFile)) die("IP Locked (1h)");
    if($_POST['p'] === $config['admin']['pass']) { clearFail($limitFile); $_SESSION['admin']=true; header("Location: ?action=admin"); }
    else { $c=logFail($limitFile); header("Location: ?action=login&rem=".(3-$c)); } exit;
}
if ($action === 'do_install') {
    $c=$defaultConfig; $c['db']=$_POST['db']; $c['admin']['pass']=$_POST['ap'];
    $pdo=new PDO("mysql:host={$c['db']['host']}",$c['db']['user'],$c['db']['pass']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$c['db']['name']}`"); $pdo->exec("USE `{$c['db']['name']}`");
    $pdo->exec("CREATE TABLE IF NOT EXISTS authme (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(255), realname VARCHAR(255), password VARCHAR(255), email VARCHAR(255), ip VARCHAR(45), lastlogin BIGINT, regdate BIGINT, reset_code VARCHAR(10), reset_time BIGINT)");
    saveConfig($c); header("Location: ?msg=installed"); exit;
}
if ($action === 'captcha') { $c=rand(1000,9999);$_SESSION['captcha']=$c;$i=imagecreatetruecolor(60,30);imagefill($i,0,0,0x3b82f6);imagestring($i,5,10,8,$c,0xffffff);header("Content-type: image/png");imagepng($i);exit; }

?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>MCS Lite</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

<?php if($action === 'home'): ?>
<div class="bg-white p-8 rounded shadow w-full max-w-md text-center">
    <h1 class="text-2xl font-bold text-blue-600 mb-6"><?= $config['site']['title'] ?></h1>
    <form action="?action=do_reg" method="POST" class="space-y-3">
        <input name="username" placeholder="游戏名" class="w-full border p-2 rounded" required>
        <input name="email" placeholder="邮箱" class="w-full border p-2 rounded" required>
        <input type="password" name="password" placeholder="密码" class="w-full border p-2 rounded" required>
        <div class="flex gap-2"><input name="captcha" placeholder="验证码" class="w-full border p-2 rounded" required><img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()"></div>
        <button class="w-full bg-blue-600 text-white p-2 rounded font-bold">注册</button>
    </form>
    <a href="?action=login" class="block mt-4 text-xs text-gray-400">后台</a>
</div>

<?php elseif($action === 'install'): ?>
<form action="?action=do_install" method="POST" class="bg-white p-8 rounded shadow w-full max-w-md">
    <h2 class="text-xl font-bold text-center mb-4">Lite 初始化</h2>
    <input name="db[host]" value="127.0.0.1" class="w-full border p-2 rounded mb-2" placeholder="DB Host">
    <input name="db[name]" value="authme" class="w-full border p-2 rounded mb-2" placeholder="DB Name">
    <input name="db[user]" class="w-full border p-2 rounded mb-2" placeholder="DB User" required>
    <input name="db[pass]" class="w-full border p-2 rounded mb-2" placeholder="DB Pass">
    <input name="ap" class="w-full border p-2 rounded mb-4" placeholder="设置后台密码" required>
    <button class="w-full bg-green-600 text-white p-2 rounded">安装</button>
</form>

<?php elseif($action === 'login'): ?>
<form action="?action=do_admin" method="POST" class="bg-white p-8 rounded shadow">
    <h2 class="font-bold mb-4">后台验证</h2>
    <?php if(isset($_GET['rem'])) echo "<p class='text-red-500 text-sm mb-2'>密码错误 (剩{$_GET['rem']}次)</p>"; ?>
    <input type="password" name="p" class="border p-2 rounded w-full mb-2" placeholder="密码">
    <button class="bg-gray-800 text-white w-full p-2 rounded">登录</button>
</form>

<?php elseif($action === 'admin' && $_SESSION['admin']): ?>
<div class="bg-white p-6 rounded shadow w-full max-w-4xl">
    <div class="flex justify-between border-b pb-4 mb-4"><h2 class="font-bold">管理控制台</h2><a href="?action=home" class="text-blue-500">首页</a></div>
    <table class="w-full text-sm text-left">
        <tr class="bg-gray-100"><th>ID</th><th>用户</th><th>邮箱</th></tr>
        <?php foreach($pdo->query("SELECT * FROM authme ORDER BY id DESC LIMIT 20") as $r): ?>
        <tr class="border-b"><td><?=$r['id']?></td><td><?=$r['realname']?></td><td><?=$r['email']?></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="mt-4 text-center text-xs text-gray-400">Lite v0.150</div>
</div>
<?php endif; ?>

</body></html>
