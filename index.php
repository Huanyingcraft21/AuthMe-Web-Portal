<?php
/**
 * Project: 流星MCS 前台
 * Version: v1.6 Final
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'core.php';
if (basename($_SERVER['PHP_SELF']) == 'config.php' || defined('IN_ADMIN')) return;

$action = $_GET['action'] ?? 'home';

// Login
if ($action === 'do_login') {
    $u = strtolower(trim($_POST['username'])); $p = $_POST['password'];
    $stmt = $pdo->prepare("SELECT id, username, password, realname FROM authme WHERE username = ?");
    $stmt->execute([$u]);
    if ($row = $stmt->fetch()) {
        if (verifyAuthMe($p, $row['password'])) { $_SESSION['user'] = $row; header("Location: ?action=user_center"); }
        else header("Location: ?action=login&msg=err_pass");
    } else header("Location: ?action=login&msg=err_user");
    exit;
}
if ($action === 'do_logout') { session_destroy(); header("Location: ?action=home"); exit; }

// Register
if ($action === 'do_reg') {
    if ($_POST['captcha'] != $_SESSION['captcha']) { header("Location: ?msg=err_captcha"); exit; }
    $u = strtolower(trim($_POST['username'])); $ip = $_SERVER['REMOTE_ADDR'];
    if ($pdo->prepare("SELECT id FROM authme WHERE username=?")->execute([$u]) && $pdo->prepare("SELECT id FROM authme WHERE username=?")->fetch()) { header("Location: ?msg=err_exists"); exit; }
    if ($pdo->prepare("SELECT id FROM authme WHERE ip=?")->execute([$ip]) && $pdo->prepare("SELECT id FROM authme WHERE ip=?")->fetch()) { header("Location: ?msg=err_ip"); exit; }
    
    $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u,$_POST['username'],hashAuthMe($_POST['password']),$_POST['email'],$ip,time()*1000,time()*1000]);
    
    if(!empty($config['rewards']['reg_cmd'])) runRcon(str_replace('%player%', $_POST['username'], $config['rewards']['reg_cmd']));
    $smtp=new TinySMTP(); 
    $smtp->send($_POST['email'], "欢迎加入", "<h3>🎉 注册成功</h3><p>欢迎加入服务器！</p>", $config['smtp']);
    if(!empty($config['admin']['email'])) $smtp->send($config['admin']['email'], "新玩家注册", "玩家: {$_POST['username']}", $config['smtp']);
    
    header("Location: ?msg=reg_ok"); exit;
}

// Sign & CDK
if ($action === 'do_sign' && isset($_SESSION['user'])) {
    $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd');
    if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; }
    if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']))) {
        setUserData($u, 'last_sign', $today);
        $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count);
        echo json_encode(['s'=>1, 'm'=>'✅ 签到成功！(累计'.$count.'天)']);
    } else { echo json_encode(['s'=>0, 'm'=>'❌ RCON失败']); } exit;
}
if ($action === 'do_cdk' && isset($_SESSION['user'])) {
    $code = trim($_POST['code']); $u = $_SESSION['user']['username']; $cdks = getCdks();
    if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; }
    $c = $cdks[$code];
    if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 已抢光']); exit; }
    if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 已领取过']); exit; }
    if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']))) {
        $c['used']++; $c['users'][] = $u; updateCdk($code, $c);
        echo json_encode(['s'=>1,'m'=>'🎁 兑换成功！']);
    } else { echo json_encode(['s'=>0,'m'=>'❌ 发放失败']); } exit;
}
if ($action === 'captcha') { $c=rand(1000,9999);$_SESSION['captcha']=$c;$i=imagecreatetruecolor(70,36);imagefill($i,0,0,0x3b82f6);imagestring($i,5,15,10,$c,0xffffff);header("Content-type: image/png");imagepng($i);exit; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title><?= htmlspecialchars($config['site']['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: url('<?= $config['site']['bg'] ?: "https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920" ?>') no-repeat center center fixed; background-size: cover; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .input { width: 100%; padding: 0.7rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; outline: none; background: rgba(255,255,255,0.8); transition: 0.2s; }
        .input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
        .btn-primary { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; font-weight: bold; padding: 0.75rem; border-radius: 0.5rem; width: 100%; transition: transform 0.1s; }
        .btn-primary:active { transform: scale(0.98); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

    <?php if(isset($_GET['msg'])): ?>
    <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
        <?= ['reg_ok'=>'🎉 注册成功！', 'err_pass'=>'🔒 密码错误', 'err_exists'=>'⚠️ 账号已存在', 'err_ip'=>'⛔ IP注册受限'][$_GET['msg']] ?? $_GET['msg'] ?>
    </div>
    <?php endif; ?>

    <?php if ($action === 'user_center' && isset($_SESSION['user'])): $user=$_SESSION['user']; $udata=getUserData($user['username']); ?>
    <div class="glass-card w-full max-w-md p-8">
        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200">
            <img src="https://cravatar.eu/helmavatar/<?=$user['realname']?>/64.png" class="w-16 h-16 rounded-xl shadow-md">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?=$user['realname']?></h2>
                <div class="text-sm text-gray-500">已签到: <span class="font-bold text-blue-600"><?=$udata['sign_count']??0?></span> 天</div>
            </div>
            <a href="?action=do_logout" class="ml-auto text-xs text-red-500 hover:bg-red-50 px-3 py-2 rounded transition">退出</a>
        </div>
        <?php if($config['rewards']['daily_cmd']): ?>
        <div class="bg-blue-50/50 p-4 rounded-xl mb-5 flex justify-between items-center border border-blue-100">
            <div><div class="font-bold text-blue-800">每日福利</div><div class="text-xs text-blue-600">Daily Reward</div></div>
            <button onclick="sign(this)" class="bg-blue-600 text-white px-5 py-2 rounded-lg font-bold shadow hover:bg-blue-700 transition"><?= ($udata['last_sign']??0)==date('Ymd') ? '已签到' : '签到' ?></button>
        </div>
        <?php endif; ?>
        <div class="space-y-2">
            <label class="text-xs font-bold text-gray-500 uppercase">CDK 兑换</label>
            <div class="flex gap-2">
                <input id="cdk" placeholder="输入礼包码..." class="input">
                <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700 transition">兑换</button>
            </div>
        </div>
        <div class="mt-8 text-center text-xs text-gray-400">RCON Connected</div>
    </div>
    <script>
    function sign(b){ b.disabled=true; b.innerText='...'; fetch('?action=do_sign').then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) b.innerText='已签到'; else b.disabled=false; }); }
    function cdk(){ let c=document.getElementById('cdk').value; if(!c)return; fetch('?action=do_cdk',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'code='+c}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); }
    </script>

    <?php elseif ($action === 'login'): ?>
    <div class="glass-card w-full max-w-sm p-8 text-center">
        <h2 class="text-2xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600">玩家登录</h2>
        <form action="?action=do_login" method="POST" class="space-y-4">
            <input name="username" placeholder="游戏角色名" class="input" required>
            <input type="password" name="password" placeholder="登录密码" class="input" required>
            <button class="btn-primary">登 录</button>
        </form>
        <div class="mt-6 text-sm"><a href="?action=home" class="text-blue-600 hover:underline">没有账号？去注册</a></div>
    </div>

    <?php else: ?>
    <div class="glass-card w-full max-w-sm p-8">
        <h1 class="text-3xl font-extrabold text-center mb-6 text-gray-800 tracking-tight"><?= htmlspecialchars($config['site']['title']) ?></h1>
        <?php if($config['server']['ip']): ?>
        <div id="status" class="hidden bg-white/60 p-2 rounded-lg mb-4 flex items-center gap-3 border border-white/40">
            <img id="icon" src="" class="w-10 h-10 rounded">
            <div class="flex-1 min-w-0">
                <div id="motd" class="text-xs text-gray-500 truncate">Loading...</div>
                <div id="online" class="text-sm font-bold text-green-600">Checking...</div>
            </div>
        </div>
        <script>
            let ip="<?=$config['server']['ip']?>", port="<?=$config['server']['port']?>";
            fetch(`https://api.mcsrvstat.us/2/${ip}:${port}`).then(r=>r.json()).then(d=>{
                document.getElementById('status').classList.remove('hidden');
                document.getElementById('icon').src = d.icon || `https://api.mcsrvstat.us/icon/${ip}`;
                document.getElementById('online').innerText = d.online ? `🟢 ${d.players.online} 人在线` : '🔴 服务器离线';
                if(d.online) document.getElementById('motd').innerText = d.motd.clean.join(' ');
            });
        </script>
        <?php endif; ?>
        <form action="?action=do_reg" method="POST" class="space-y-3">
            <input name="username" placeholder="Minecraft 角色名" class="input" required>
            <input name="email" type="email" placeholder="电子邮箱" class="input" required>
            <input type="password" name="password" placeholder="设置密码" class="input" required>
            <div class="flex gap-2">
                <input name="captcha" placeholder="验证码" class="input" required>
                <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded cursor-pointer border border-gray-200">
            </div>
            <button class="btn-primary mt-2">立即注册</button>
        </form>
        <div class="mt-6 flex justify-between text-sm">
            <a href="?action=login" class="text-blue-600 font-bold hover:underline">已有账号？登录</a>
            <a href="#" onclick="alert('请联系腐竹重置密码')" class="text-gray-400 hover:text-gray-600">忘记密码?</a>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
