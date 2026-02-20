<?php
/**
 * Project: 流星MCS 标准版前台
 * Version: v1.9.1 (Safe Connection Edition)
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'core.php'; 

if (basename($_SERVER['PHP_SELF']) == 'config.php' || defined('IN_ADMIN')) return;
$A = $_GET['action'] ?? 'home';

if ($A === 'do_login') {
    if (!$pdo) { header("Location: ?action=login&msg=err_db"); exit; } // 数据库断开拦截，防崩溃
    $u = strtolower(trim($_POST['username'])); $p = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM authme WHERE username=?");
    $stmt->execute([$u]);
    if ($r = $stmt->fetch()) {
        if (verifyAuthMe($p, $r['password'])) {
            $_SESSION['user'] = $r; header("Location: ?action=user_center");
        } else header("Location: ?action=login&msg=err_pass");
    } else header("Location: ?action=login&msg=err_user"); exit;
}

if ($A === 'do_logout') { session_destroy(); header("Location: ?action=home"); exit; }

if ($A === 'do_reg') {
    if (!$pdo) { header("Location: ?action=login&msg=err_db"); exit; } // 数据库断开拦截
    if (empty($_SESSION['captcha']) || $_POST['captcha'] != $_SESSION['captcha']) { header("Location: ?msg=err_captcha"); exit; }
    $u = strtolower(trim($_POST['username'])); $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("SELECT id FROM authme WHERE username=?");
    $stmt->execute([$u]);
    if ($stmt->fetch()) { header("Location: ?msg=err_exists"); exit; }
    
    $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")
        ->execute([$u, $_POST['username'], hashAuthMe($_POST['password']), $_POST['email'], $ip, time()*1000, time()*1000]);
    
    if (!empty($config['rewards']['reg_cmd'])) { runApiCmd(str_replace('%player%', $_POST['username'], $config['rewards']['reg_cmd']), 0); }
    
    $smtp = new TinySMTP(); $smtp->send($_POST['email'], "欢迎加入", "恭喜注册成功！", $config['smtp']);
    header("Location: ?msg=reg_ok"); exit;
}

if ($A === 'do_sign' && isset($_SESSION['user'])) {
    $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd');
    if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; }
    
    $targets = $config['rewards']['sign_in_servers'] ?? []; $ok = 0;
    foreach ($targets as $sid) {
        if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']), $sid)) $ok++;
    }
    
    if ($ok > 0) {
        setUserData($u, 'last_sign', $today);
        $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count);
        echo json_encode(['s'=>1, 'm'=>"✅ 签到成功 (发放至 $ok 个服务器)"]);
    } else { echo json_encode(['s'=>0, 'm'=>'❌ 服务器 MetorCore API 握手失败']); } exit;
}

if ($A === 'do_cdk' && isset($_SESSION['user'])) {
    $code = trim($_POST['code']); $srvIdx = (int)$_POST['server_id'];
    $u = $_SESSION['user']['username']; $cdks = getCdks();
    
    if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; }
    $c = $cdks[$code];
    if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 已被抢光']); exit; }
    if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 您已领取过']); exit; }
    if (isset($c['server_id']) && $c['server_id'] !== 'all' && (int)$c['server_id'] !== $srvIdx) { echo json_encode(['s'=>0,'m'=>'❌ 此CDK不适用于该服务器']); exit; }
    
    $targetSrv = ($c['server_id'] === 'all') ? $srvIdx : (int)$c['server_id'];
    
    if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']), $targetSrv)) {
        $c['used']++; $c['users'][] = $u; updateCdk($code, $c);
        echo json_encode(['s'=>1,'m'=>'🎁 兑换成功！']);
    } else { echo json_encode(['s'=>0,'m'=>'❌ MetorCore 发放失败']); } exit;
}

if ($A === 'do_fp_send') {
    if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开，无法操作']); exit; }
    $u = strtolower(trim($_POST['u'])); $e = trim($_POST['e']);
    $stmt = $pdo->prepare("SELECT id, email FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch();
    if (!$r || $r['email'] !== $e) { echo json_encode(['s'=>0, 'm'=>'❌ 用户名与邮箱不匹配']); exit; }
    
    $code = rand(100000, 999999); $t = time();
    try {
        $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]);
    } catch (PDOException $e) {
        if ($e->getCode() == '42S22') { 
            $pdo->exec("ALTER TABLE authme ADD COLUMN reset_code VARCHAR(10), ADD COLUMN reset_time BIGINT");
            $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]);
        } else { echo json_encode(['s'=>0, 'm'=>'❌ 数据库异常']); exit; }
    }
    
    $smtp = new TinySMTP(); $smtp->send($e, "重置密码验证码", "您的验证码是: <b>$code</b> (10分钟内有效)", $config['smtp']);
    echo json_encode(['s'=>1, 'm'=>'✅ 验证码已发送至邮箱']); exit;
}

if ($A === 'do_fp_reset') {
    if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开，无法操作']); exit; }
    $u = strtolower(trim($_POST['u'])); $c = trim($_POST['code']); $p = $_POST['pass'];
    $stmt = $pdo->prepare("SELECT id, reset_code, reset_time FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch();
    if (!$r || $r['reset_code'] !== $c) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码错误']); exit; }
    if (time() - $r['reset_time'] > 600) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码已过期']); exit; }
    $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE id=?")->execute([hashAuthMe($p), $r['id']]);
    echo json_encode(['s'=>1, 'm'=>'🎉 密码修改成功！请登录']); exit;
}

if ($A === 'captcha') { 
    $c=rand(1000,9999); $_SESSION['captcha']=$c;
    $i=imagecreatetruecolor(70,36); imagefill($i,0,0,0x3b82f6); imagestring($i,5,15,10,$c,0xffffff);
    header("Content-type: image/png"); imagepng($i); exit; 
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
        body { background: url('<?= $config['site']['bg'] ?: "https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920" ?>') no-repeat center center fixed; background-size: cover; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); border: 1px solid rgba(255,255,255,0.5); }
        .input { width: 100%; padding: 0.7rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: rgba(255,255,255,0.8); outline: none; transition: 0.2s; }
        .input:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
        .btn-primary { background: #2563eb; color: white; font-weight: bold; padding: 0.75rem; border-radius: 0.5rem; width: 100%; transition: transform 0.1s; }
        .btn-primary:active { transform: scale(0.98); }
        .hidden { display: none; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

    <?php if(isset($_GET['msg'])): ?>
    <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
        <?= ['reg_ok'=>'🎉 注册成功！', 'err_pass'=>'🔒 密码错误', 'err_exists'=>'⚠️ 账号已存在', 'err_captcha'=>'❌ 验证码错误', 'err_db'=>'❌ 数据库连接异常'][$_GET['msg']] ?? $_GET['msg'] ?>
    </div>
    <?php endif; ?>

    <?php if ($A === 'user_center' && isset($_SESSION['user'])): $user=$_SESSION['user']; $udata=getUserData($user['username']); ?>
    <div class="glass-card w-full max-w-md p-8 fade-in">
        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200">
            <img src="https://cravatar.eu/helmavatar/<?=$user['realname']?>/64.png" class="w-16 h-16 rounded-xl shadow-md">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?=$user['realname']?></h2>
                <div class="text-sm text-gray-500">签到: <span class="font-bold text-blue-600"><?=$udata['sign_count']??0?></span> 天</div>
            </div>
            <a href="?action=do_logout" class="ml-auto text-xs bg-red-50 text-red-500 px-3 py-2 rounded hover:bg-red-100 transition">退出</a>
        </div>

        <button onclick="sign(this)" class="w-full mb-6 py-3 rounded-xl font-bold shadow transition border <?= ($udata['last_sign']??0)==date('Ymd') ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-indigo-50 text-indigo-600 border-indigo-100 hover:bg-indigo-100' ?>">
            <?= ($udata['last_sign']??0)==date('Ymd') ? '✅ 今日已签到' : '📅 每日签到' ?>
        </button>

        <div class="space-y-3">
            <label class="text-xs font-bold text-gray-400 uppercase">CDK 兑换</label>
            <select id="sel_srv" class="input font-bold text-blue-900">
                <?php foreach($config['servers'] as $idx => $srv): ?>
                    <option value="<?=$idx?>">🌍 <?= htmlspecialchars($srv['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <input id="cdk" placeholder="输入兑换码..." class="input">
                <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700 transition">兑换</button>
            </div>
        </div>
    </div>
    <script>
    function sign(b){ b.disabled=true; b.innerText='...'; fetch('?action=do_sign').then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) { b.innerText='✅ 已签到'; b.className='w-full mb-6 py-3 rounded-xl font-bold shadow transition border bg-gray-100 text-gray-400 cursor-not-allowed'; } else b.disabled=false; }); }
    function cdk(){ let c=document.getElementById('cdk').value; let s=document.getElementById('sel_srv').value; if(!c)return; fetch('?action=do_cdk',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`code=${c}&server_id=${s}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); }
    </script>

    <?php else: ?>
    <div class="glass-card w-full max-w-sm p-8 text-center relative fade-in">
        <h1 class="text-3xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600 pb-1">
            <?= htmlspecialchars($config['site']['title']) ?>
        </h1>

        <?php if(!empty($config['display']['ip'])): ?>
        <div id="status" class="hidden bg-white/60 p-2 rounded-lg mb-6 flex items-center gap-3 border border-white/50 text-left">
            <img id="icon" src="" class="w-10 h-10 rounded bg-gray-200">
            <div class="flex-1 min-w-0">
                <div id="motd" class="text-xs text-gray-500 truncate">Loading...</div>
                <div id="online" class="text-sm font-bold text-green-600">Connecting...</div>
            </div>
        </div>
        <script>
            fetch('https://api.mcsrvstat.us/2/<?= $config['display']['ip'] ?>:<?= $config['display']['port'] ?>').then(r=>r.json()).then(d=>{
                document.getElementById('status').classList.remove('hidden');
                document.getElementById('icon').src = d.icon || `https://api.mcsrvstat.us/icon/<?= $config['display']['ip'] ?>`;
                document.getElementById('online').innerText = d.online ? `🟢 ${d.players.online} 人在线` : '🔴 服务器离线';
                if(d.online) document.getElementById('motd').innerText = d.motd.clean.join(' ');
            });
        </script>
        <?php endif; ?>
        
        <div id="box-reg">
            <h2 class="text-xl font-bold text-gray-700 mb-4">新用户注册</h2>
            <form action="?action=do_reg" method="POST" class="space-y-3">
                <input name="username" placeholder="Minecraft 角色名" class="input" required>
                <input name="email" type="email" placeholder="电子邮箱 (用于找回密码)" class="input" required>
                <input type="password" name="password" placeholder="设置密码" class="input" required>
                <div class="flex gap-2">
                    <input name="captcha" placeholder="验证码" class="input" required>
                    <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded cursor-pointer border border-gray-200">
                </div>
                <button class="btn-primary mt-2 bg-gradient-to-r from-green-500 to-emerald-600 border-none">确认注册</button>
            </form>
            <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">已有账号？点击登录</a></p>
        </div>

        <div id="box-login" class="hidden">
            <h2 class="text-xl font-bold text-gray-700 mb-4">玩家登录</h2>
            <form action="?action=do_login" method="POST" class="space-y-4">
                <input name="username" placeholder="游戏角色名" class="input" required>
                <input type="password" name="password" placeholder="密码" class="input" required>
                <button class="btn-primary shadow-lg shadow-blue-500/30">立即登录</button>
            </form>
            <div class="mt-6 flex justify-between text-sm">
                <a href="#" onclick="toggle('box-reg')" class="text-gray-400 hover:text-gray-600">注册账号</a>
                <a href="#" onclick="toggle('box-fp')" class="text-blue-600 font-bold hover:underline">忘记密码?</a>
            </div>
        </div>

        <div id="box-fp" class="hidden">
            <h2 class="text-xl font-bold text-gray-700 mb-4">重置密码</h2>
            <div class="space-y-3 text-left">
                <input id="fp_u" placeholder="您的游戏名" class="input">
                <div class="flex gap-2">
                    <input id="fp_e" placeholder="绑定的邮箱" class="input">
                    <button onclick="sendCode()" class="bg-gray-500 text-white px-3 rounded text-xs whitespace-nowrap hover:bg-gray-600">发送验证码</button>
                </div>
                <input id="fp_c" placeholder="邮箱收到的验证码" class="input">
                <input id="fp_p" type="password" placeholder="设置新密码" class="input">
                <button onclick="doReset()" class="btn-primary bg-orange-500 hover:bg-orange-600 border-none">提交重置</button>
            </div>
            <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">返回登录</a></p>
        </div>
    </div>
    <script>
    function toggle(id) { 
        ['box-login','box-reg','box-fp'].forEach(x => document.getElementById(x).classList.add('hidden')); 
        document.getElementById(id).classList.remove('hidden'); 
    }
    function sendCode() {
        let u=document.getElementById('fp_u').value, e=document.getElementById('fp_e').value;
        if(!u || !e) { alert('请填写用户名和邮箱'); return; }
        fetch('?action=do_fp_send', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&e=${e}` })
        .then(r=>r.json()).then(d => alert(d.m));
    }
    function doReset() {
        let u=document.getElementById('fp_u').value, c=document.getElementById('fp_c').value, p=document.getElementById('fp_p').value;
        if(!c || !p) { alert('请填写完整信息'); return; }
        fetch('?action=do_fp_reset', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&code=${c}&pass=${p}` })
        .then(r=>r.json()).then(d => { alert(d.m); if(d.s) toggle('box-login'); });
    }
    </script>
    <?php endif; ?>
</body>
</html>
