<?php
/**
 * Project: 流星MCS Lite (No-Install Version)
 * Version: v1.6 Lite
 */
session_start();
error_reporting(0);
header('Content-Type: text/html; charset=utf-8');

$configFile = 'config.php';
// 🔥 Lite版不再包含安装逻辑，如果没配置，直接报错
if (!file_exists($configFile)) die("<h2>Error</h2><p>Lite版需要先运行 install.php 进行安装。</p>");

// --- 迷你 Core ---
$config = include($configFile);
$pdo = null;
try { $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['name']}", $config['db']['user'], $config['db']['pass']); } catch(Exception $e){}
function hashAuthMe($p) { $s = bin2hex(random_bytes(8)); return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s); }
class TinySMTP {
    private $sock;
    public function send($to,$sub,$body,$conf){
        if(!$to)return false; $h=($conf['secure']=='ssl'?'ssl://':'').$conf['host']; $this->sock=fsockopen($h,$conf['port']); if(!$this->sock)return false;
        $this->cmd(NULL); $this->cmd("EHLO ".$conf['host']); $this->cmd("AUTH LOGIN"); $this->cmd(base64_encode($conf['user'])); $this->cmd(base64_encode($conf['pass']));
        $this->cmd("MAIL FROM: <{$conf['user']}>"); $this->cmd("RCPT TO: <$to>"); $this->cmd("DATA");
        fwrite($this->sock,"MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?".base64_encode($conf['from_name'])."?= <{$conf['user']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?".base64_encode($sub)."?=\r\n\r\n$body\r\n.\r\n");
        $this->cmd("QUIT"); fclose($this->sock); return true;
    }
    private function cmd($c){ if($c)fwrite($this->sock,$c."\r\n"); while($s=fgets($this->sock,515)){if(substr($s,3,1)==" ")break;} }
}
// ----------------

$action = $_GET['action'] ?? 'home';

if ($action === 'do_reg') {
    if ($_POST['captcha'] != $_SESSION['captcha']) die("验证码错误 <a href='?'>返回</a>");
    $u = strtolower(trim($_POST['username'])); $ip = $_SERVER['REMOTE_ADDR'];
    if ($pdo->prepare("SELECT id FROM authme WHERE username=?")->execute([$u]) && $pdo->prepare("SELECT id FROM authme WHERE username=?")->fetch()) die("用户已存在 <a href='?'>返回</a>");
    $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u,$_POST['username'],hashAuthMe($_POST['password']),$_POST['email'],$ip,time()*1000,time()*1000]);
    $smtp=new TinySMTP(); $smtp->send($_POST['email'], "注册成功", "欢迎加入！", $config['smtp']);
    if($config['admin']['email']) $smtp->send($config['admin']['email'], "新用户", "User: $u", $config['smtp']);
    die("注册成功！<a href='?'>返回</a>");
}
if ($action === 'captcha') { $c=rand(1000,9999);$_SESSION['captcha']=$c;$i=imagecreatetruecolor(60,30);imagefill($i,0,0,0x3b82f6);imagestring($i,5,10,8,$c,0xffffff);header("Content-type: image/png");imagepng($i);exit; }
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= $config['site']['title'] ?></title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
<div class="bg-white p-8 rounded shadow-lg w-full max-w-sm">
    <h1 class="text-xl font-bold text-center mb-6"><?= $config['site']['title'] ?> Lite</h1>
    <form action="?action=do_reg" method="POST" class="space-y-4">
        <input name="username" placeholder="游戏ID" class="w-full border p-2 rounded" required>
        <input name="email" placeholder="邮箱" class="w-full border p-2 rounded" required>
        <input type="password" name="password" placeholder="密码" class="w-full border p-2 rounded" required>
        <div class="flex gap-2"><input name="captcha" placeholder="验证码" class="w-full border p-2 rounded" required><img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()"></div>
        <button class="w-full bg-blue-600 text-white p-2 rounded">注册</button>
    </form>
</div>
</body></html>
