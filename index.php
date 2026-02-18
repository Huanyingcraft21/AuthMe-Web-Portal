<?php
/**
 * Project: 流星MCS 前台 v1.7
 */
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'core.php';
if (basename($_SERVER['PHP_SELF']) == 'config.php' || defined('IN_ADMIN')) return;
$action = $_GET['action'] ?? 'home';

// Login/Reg (Same)
if ($action === 'do_login') { $u=strtolower(trim($_POST['username'])); $p=$_POST['password']; $stmt=$pdo->prepare("SELECT * FROM authme WHERE username=?"); $stmt->execute([$u]); if($r=$stmt->fetch()){ if(verifyAuthMe($p,$r['password'])){ $_SESSION['user']=$r; header("Location: ?action=user_center"); }else header("Location: ?action=login&msg=err_pass"); }else header("Location: ?action=login&msg=err_user"); exit; }
if ($action === 'do_logout') { session_destroy(); header("Location: ?action=home"); exit; }
if ($action === 'do_reg') { $u=strtolower(trim($_POST['username'])); $ip=$_SERVER['REMOTE_ADDR']; $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u,$_POST['username'],hashAuthMe($_POST['password']),$_POST['email'],$ip,time()*1000,time()*1000]); if(!empty($config['rewards']['reg_cmd'])) runRcon(str_replace('%player%',$_POST['username'],$config['rewards']['reg_cmd']), 0); header("Location: ?msg=reg_ok"); exit; }

// [Revised] 签到: 批量发给配置的服务器
if ($action === 'do_sign' && isset($_SESSION['user'])) {
    $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd');
    if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; }
    
    // 获取后台配置的目标服务器列表
    $targetIDs = $config['rewards']['sign_in_servers'] ?? [];
    if (empty($targetIDs)) { echo json_encode(['s'=>0, 'm'=>'❌ 管理员未配置签到服务器']); exit; }
    
    $successCount = 0;
    foreach ($targetIDs as $sid) {
        if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']), $sid)) {
            $successCount++;
        }
    }
    
    if ($successCount > 0) {
        setUserData($u, 'last_sign', $today);
        $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count);
        echo json_encode(['s'=>1, 'm'=>"✅ 签到成功！奖励已发往 $successCount 个服务器"]);
    } else {
        echo json_encode(['s'=>0, 'm'=>'❌ 所有服务器连接失败']);
    }
    exit;
}

// [Revised] CDK: 必须选服
if ($action === 'do_cdk' && isset($_SESSION['user'])) {
    $code = trim($_POST['code']); $srvIdx = (int)$_POST['server_id'];
    $u = $_SESSION['user']['username']; $cdks = getCdks();
    
    if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; }
    $c = $cdks[$code];
    if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 已被抢光']); exit; }
    if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 您已领取过']); exit; }
    
    // 校验绑定
    if (isset($c['server_id']) && $c['server_id'] !== 'all' && (int)$c['server_id'] !== $srvIdx) {
        echo json_encode(['s'=>0,'m'=>'❌ 此CDK不适用于该服务器']); exit;
    }
    
    $targetSrv = ($c['server_id'] === 'all') ? $srvIdx : (int)$c['server_id'];
    if (runRcon(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']), $targetSrv)) {
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
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 1rem; box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37); }
        .input { width: 100%; padding: 0.7rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; background: rgba(255,255,255,0.8); }
        .btn-primary { background: #2563eb; color: white; font-weight: bold; padding: 0.75rem; border-radius: 0.5rem; width: 100%; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

    <?php if(isset($_GET['msg'])): ?><div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 bg-blue-600"><?= $_GET['msg'] ?></div><?php endif; ?>

    <?php if ($action === 'user_center' && isset($_SESSION['user'])): $user=$_SESSION['user']; $udata=getUserData($user['username']); ?>
    <div class="glass-card w-full max-w-md p-8">
        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-200">
            <img src="https://cravatar.eu/helmavatar/<?=$user['realname']?>/64.png" class="w-16 h-16 rounded-xl shadow-md">
            <div>
                <h2 class="text-xl font-bold text-gray-800"><?=$user['realname']?></h2>
                <div class="text-sm text-gray-500">累计签到: <span class="font-bold text-blue-600"><?=$udata['sign_count']??0?></span> 天</div>
            </div>
            <a href="?action=do_logout" class="ml-auto text-xs text-red-500 bg-red-50 px-3 py-2 rounded">退出</a>
        </div>

        <?php if($config['rewards']['daily_cmd']): ?>
        <div class="bg-indigo-50 p-4 rounded-xl mb-5 flex justify-between items-center border border-indigo-100">
            <div><div class="font-bold text-indigo-800">每日签到</div><div class="text-xs text-indigo-600">全服奖励同步发放</div></div>
            <button onclick="sign(this)" class="bg-indigo-600 text-white px-5 py-2 rounded-lg font-bold shadow hover:bg-indigo-700">
                <?= ($udata['last_sign']??0)==date('Ymd') ? '已签到' : '签到' ?>
            </button>
        </div>
        <?php endif; ?>

        <div class="space-y-2 border-t pt-4">
            <label class="text-xs font-bold text-gray-500 uppercase">CDK 兑换</label>
            <select id="sel_srv" class="input font-bold text-blue-800 mb-2">
                <?php foreach($config['servers'] as $idx => $srv): ?>
                    <option value="<?=$idx?>">🌍 <?= htmlspecialchars($srv['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <input id="cdk" placeholder="输入兑换码..." class="input">
                <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700">兑换</button>
            </div>
        </div>
    </div>
    <script>
    function sign(b){ 
        b.disabled=true; b.innerText='...'; 
        fetch('?action=do_sign').then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) b.innerText='已签到'; else b.disabled=false; }); 
    }
    function cdk(){ 
        let c=document.getElementById('cdk').value; 
        let srv = document.getElementById('sel_srv').value;
        if(!c)return; 
        fetch('?action=do_cdk',{
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`code=${c}&server_id=${srv}`
        }).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); 
    }
    </script>

    <?php elseif ($action === 'login'): ?>
    <div class="glass-card w-full max-w-sm p-8 text-center">
        <h2 class="text-2xl font-bold mb-6">玩家登录</h2>
        <form action="?action=do_login" method="POST" class="space-y-4"><input name="username" placeholder="游戏角色名" class="input" required><input type="password" name="password" placeholder="密码" class="input" required><button class="btn-primary">登 录</button></form>
        <div class="mt-6 text-sm"><a href="?action=home" class="text-blue-600">去注册</a></div>
    </div>
    <?php else: ?>
    <div class="glass-card w-full max-w-sm p-8">
        <h1 class="text-3xl font-extrabold text-center mb-6"><?= htmlspecialchars($config['site']['title']) ?></h1>
        <form action="?action=do_reg" method="POST" class="space-y-3"><input name="username" placeholder="角色名" class="input" required><input name="email" placeholder="邮箱" class="input" required><input type="password" name="password" placeholder="密码" class="input" required><div class="flex gap-2"><input name="captcha" placeholder="验证码" class="input" required><img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded border"></div><button class="btn-primary mt-2">立即注册</button></form>
        <div class="mt-6 flex justify-between text-sm"><a href="?action=login" class="text-blue-600 font-bold">登陆用户中心</a></div>
    </div>
    <?php endif; ?>
</body>
</html>
