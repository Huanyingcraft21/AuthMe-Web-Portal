<?php
/**
 * Project: 流星MCS 账号管理器 (Standard)
 * Version: v1.5
 * Note: 前台核心 (注册/找回/公共库)
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
error_reporting(0);

$configFile = 'config.php';

// --- 安全检查 ---
if (!file_exists($configFile)) {
    die("<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding-top:50px;'>
    <h1 style='color:#eab308;'>⚠️ 系统未初始化</h1>
    <p>请上传 <b>install.php</b> 并访问它进行安装。</p>
    </body></html>");
}

if (basename($_SERVER['PHP_SELF']) == $configFile) die('Access Denied');

// --- 加载配置 ---
$defaultConfig = [
    'db' => ['host'=>'127.0.0.1', 'name'=>'authme', 'user'=>'root', 'pass'=>''],
    'smtp' => ['host'=>'smtp.qq.com', 'port'=>465, 'user'=>'', 'pass'=>'', 'secure'=>'ssl', 'from_name'=>'流星MCS'],
    'admin' => ['user'=>'admin', 'pass'=>'password123'],
    'site' => ['title'=>'流星MCS玩家注册', 'ver'=>'v1.5']
];
$loaded = include($configFile);
$config = isset($loaded['host']) ? array_replace_recursive($defaultConfig, ['db'=>$loaded]) : array_replace_recursive($defaultConfig, $loaded);

// --- 核心库 ---
function saveConfig($newConfig) {
    global $configFile;
    return file_put_contents($configFile, "<?php\nreturn " . var_export($newConfig, true) . ";");
}
function hashAuthMe($p) {
    $s = bin2hex(random_bytes(8));
    return "\$SHA\$" . $s . "\$" . hash('sha256', hash('sha256', $p) . $s);
}
class TinySMTP {
    private $sock;
    public function send($to, $subject, $body, $conf) {
        $host = ($conf['secure'] == 'ssl' ? 'ssl://' : '') . $conf['host'];
        $this->sock = fsockopen($host, $conf['port'], $errno, $errstr, 10);
        if (!$this->sock) return false;
        $this->cmd(NULL); $this->cmd("EHLO " . $conf['host']); $this->cmd("AUTH LOGIN");
        $this->cmd(base64_encode($conf['user'])); $this->cmd(base64_encode($conf['pass']));
        $this->cmd("MAIL FROM: <{$conf['user']}>"); $this->cmd("RCPT TO: <$to>"); $this->cmd("DATA");
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?".base64_encode($conf['from_name'])."?= <{$conf['user']}>\r\nTo: $to\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
        fwrite($this->sock, "$headers\r\n$body\r\n.\r\n");
        $res = $this->get_lines(); $this->cmd("QUIT"); fclose($this->sock);
        return strpos($res, "250") !== false;
    }
    private function cmd($c) { if($c) fwrite($this->sock, $c."\r\n"); return $this->get_lines(); }
    private function get_lines() { $d=""; while($s=fgets($this->sock,515)){$d.=$s; if(substr($s,3,1)==" ")break;} return $d; }
}

// --- DB连接 ---
$pdo = null;
if (!empty($config['db']['name'])) {
    try {
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {}
}

if (defined('IN_ADMIN')) return; 

// --- 前端逻辑 ---
$action = $_GET['action'] ?? 'home';

if ($action === 'do_reg') {
    if ($_POST['captcha'] != $_SESSION['captcha']) { header("Location: ?msg=err_captcha"); exit; }
    $u = strtolower(trim($_POST['username']));
    if ($pdo->prepare("SELECT id FROM authme WHERE username=?")->execute([$u]) && $pdo->prepare("SELECT id FROM authme WHERE username=?")->fetch()) {
        header("Location: ?msg=err_exists"); exit;
    }
    $pdo->prepare("INSERT INTO authme (username, realname, password, email, ip, regdate, lastlogin) VALUES (?,?,?,?,?,?,?)")
        ->execute([$u, $_POST['username'], hashAuthMe($_POST['password']), $_POST['email'], $_SERVER['REMOTE_ADDR'], time()*1000, time()*1000]);
    header("Location: ?msg=reg_ok"); exit;
}
if ($action === 'do_send_code') {
    $email = $_POST['email'];
    $u = $pdo->prepare("SELECT id FROM authme WHERE email = ?"); $u->execute([$email]);
    if ($row = $u->fetch()) {
        $code = rand(100000, 999999);
        $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, time()+300, $row['id']]);
        $smtp = new TinySMTP();
        $res = $smtp->send($email, "密码重置", "验证码：<b style='color:blue'>$code</b>", $config['smtp']);
        echo json_encode(['status' => $res?'ok':'err', 'msg' => $res?'发送成功':'发送失败']);
    } else { echo json_encode(['status'=>'err', 'msg'=>'邮箱未注册']); } exit;
}
if ($action === 'do_reset') {
    if ($pdo->prepare("SELECT id FROM authme WHERE email=? AND reset_code=? AND reset_time>?")->execute([$_POST['email'], $_POST['code'], time()]) && $pdo->prepare("SELECT id FROM authme WHERE email=? AND reset_code=? AND reset_time>?")->fetchAll()) {
        $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE email=?")->execute([hashAuthMe($_POST['password']), $_POST['email']]);
        header("Location: ?msg=reset_ok");
    } else { header("Location: ?action=forgot&msg=err_code"); } exit;
}
if ($action === 'captcha') {
    $c = (string)rand(1000, 9999); $_SESSION['captcha'] = $c;
    $i = imagecreatetruecolor(70, 35); imagefill($i, 0, 0, 0x3b82f6); imagestring($i, 5, 15, 10, $c, 0xffffff); header("Content-type: image/png"); imagepng($i); imagedestroy($i); exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site']['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #f3f4f6; min-height: 100vh; font-family: sans-serif; }
        .center-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
        .card { background: white; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .input { width: 100%; padding: 0.6rem; border: 1px solid #e5e7eb; border-radius: 0.5rem; outline: none; transition: 0.2s; background: #fff; }
        .input:focus { border-color: #3b82f6; ring: 2px solid #3b82f6; }
        .btn { width: 100%; padding: 0.75rem; background: #2563eb; color: white; border-radius: 0.5rem; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #1d4ed8; }
        .main-title { background: linear-gradient(to right, #2563eb, #3b82f6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body>
    <?php if(isset($_GET['msg'])): ?>
    <div class="fixed top-5 left-1/2 -translate-x-1/2 px-4 py-2 rounded shadow text-white text-sm font-bold z-50
        <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
        <?= ['reg_ok'=>'🎉 注册成功！', 'reset_ok'=>'✅ 密码已重置', 'err_exists'=>'⚠️ 用户名已存在', 'err_captcha'=>'❌ 验证码错误'][$_GET['msg']] ?? '操作完成' ?>
    </div>
    <?php endif; ?>

    <?php if ($action === 'home'): ?>
    <div class="center-wrap"><div class="card">
        <h1 class="text-3xl font-extrabold text-center mb-2 main-title"><?= htmlspecialchars($config['site']['title']) ?></h1>
        <p class="text-center text-gray-400 text-sm mb-6">Create Account</p>
        <form action="?action=do_reg" method="POST" class="space-y-3">
            <input type="text" name="username" placeholder="游戏角色名" class="input" required>
            <input type="email" name="email" placeholder="电子邮箱" class="input" required>
            <input type="password" name="password" placeholder="密码" class="input" required>
            <div class="flex gap-2"><input type="text" name="captcha" placeholder="验证码" class="input" required>
            <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-10 rounded border cursor-pointer"></div>
            <button class="btn mt-2">立即注册</button>
        </form>
        <div class="mt-6 text-center text-sm"><a href="?action=forgot" class="text-gray-500 hover:text-blue-600">忘记密码</a></div>
    </div></div>
    
    <?php elseif ($action === 'forgot'): ?>
    <div class="center-wrap"><div class="card">
        <h2 class="text-xl font-bold text-center mb-4">重置密码</h2>
        <form action="?action=do_reset" method="POST" class="space-y-3">
            <div class="flex gap-2"><input type="email" id="m" name="email" placeholder="邮箱" class="input w-full"><button type="button" onclick="sc(this)" class="bg-blue-100 text-blue-600 px-3 rounded text-xs font-bold whitespace-nowrap">发验证码</button></div>
            <input type="text" name="code" placeholder="6位验证码" class="input">
            <input type="password" name="password" placeholder="新密码" class="input">
            <button class="btn">提交</button>
        </form>
        <div class="mt-4 text-center"><a href="?action=home" class="text-sm text-gray-500">返回</a></div>
    </div></div><script>function sc(b){var m=document.getElementById('m').value;if(!m)return alert('填邮箱');b.disabled=true;b.innerText='...';fetch('?action=do_send_code',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'email='+m}).then(r=>r.json()).then(d=>{alert(d.msg);if(d.status=='ok'){var s=60,t=setInterval(()=>{b.innerText=s--;if(s<0){clearInterval(t);b.disabled=false;b.innerText='发送'}},1000)}else{b.disabled=false;b.innerText='发送'}})}</script>
    <?php endif; ?>
</body>
</html>
