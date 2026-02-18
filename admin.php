<?php
/**
 * Project: 流星MCS 标准版前台
 * Version: v1.8 (With Password Reset)
 */
session_start();header('Content-Type: text/html; charset=utf-8');
require_once 'core.php';
if (basename($_SERVER['PHP_SELF']) == 'config.php' || defined('IN_ADMIN')) return;
$A = $_GET['action'] ?? 'home';

// Logic Handlers
if ($A === 'do_login') { $u=strtolower(trim($_POST['u'])); $p=$_POST['p']; $stmt=$pdo->prepare("SELECT * FROM authme WHERE username=?"); $stmt->execute([$u]); if($r=$stmt->fetch()){ if(verifyAuthMe($p,$r['password'])){ $_SESSION['user']=$r; header("Location: ?action=user_center"); }else header("Location: ?action=login&msg=err_pass"); }else header("Location: ?action=login&msg=err_user"); exit; }
if ($A === 'do_logout') { session_destroy(); header("Location: ?action=home"); exit; }
if ($A === 'do_reg') { $u=strtolower(trim($_POST['u'])); $ip=$_SERVER['REMOTE_ADDR']; $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u,$_POST['u'],hashAuthMe($_POST['p']),$_POST['e'],$ip,time()*1000,time()*1000]); if($c=$config['rewards']['reg_cmd']) runRcon(str_replace('%player%',$_POST['u'],$c), 0); header("Location: ?msg=reg_ok"); exit; }

// Sign & CDK (Omitted for brevity, logic same as before)
if ($A === 'do_sign') { /* ...Same as v1.7.5... */ exit; }
if ($A === 'do_cdk') { /* ...Same as v1.7.5... */ exit; }

// 🔥 Forgot Password Logic
if ($A === 'do_fp_send') {
    $u=strtolower(trim($_POST['u'])); $e=trim($_POST['e']);
    $stmt=$pdo->prepare("SELECT id,email FROM authme WHERE username=?"); $stmt->execute([$u]); $r=$stmt->fetch();
    if(!$r || $r['email']!==$e) { echo json_encode(['s'=>0,'m'=>'❌ 用户名和邮箱不匹配']); exit; }
    $code=rand(100000,999999); $t=time();
    $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]);
    $smtp = new TinySMTP(); $smtp->send($e, "重置验证码", "Code: <b>$code</b> (10 min)", $config['smtp']);
    echo json_encode(['s'=>1,'m'=>'✅ 验证码已发送']); exit;
}
if ($A === 'do_fp_reset') {
    $u=strtolower(trim($_POST['u'])); $c=trim($_POST['c']); $p=$_POST['p'];
    $stmt=$pdo->prepare("SELECT id,reset_code,reset_time FROM authme WHERE username=?"); $stmt->execute([$u]); $r=$stmt->fetch();
    if(!$r || $r['reset_code']!==$c) { echo json_encode(['s'=>0,'m'=>'❌ 验证码错误']); exit; }
    if(time()-$r['reset_time']>600) { echo json_encode(['s'=>0,'m'=>'❌ 验证码已过期']); exit; }
    $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE id=?")->execute([hashAuthMe($p), $r['id']]);
    echo json_encode(['s'=>1,'m'=>'🎉 密码修改成功']); exit;
}
if ($A === 'captcha') { $c=rand(1000,9999);$_SESSION['captcha']=$c;$i=imagecreatetruecolor(60,34);imagefill($i,0,0,0x3b82f6);imagestring($i,5,12,9,$c,0xffffff);header("Content-type: image/png");imagepng($i);exit; }
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title><?= htmlspecialchars($config['site']['title']) ?></title><script src="https://cdn.tailwindcss.com"></script><style>body{background:url('<?= $config['site']['bg'] ?>') center/cover fixed}.glass-card{background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-radius:1rem;box-shadow:0 8px 32px rgba(0,0,0,0.2)}.input{width:100%;padding:0.7rem;border:1px solid #e2e8f0;border-radius:0.5rem;background:rgba(255,255,255,0.8)}.btn-primary{background:#2563eb;color:white;font-weight:bold;padding:0.75rem;border-radius:0.5rem;width:100%}.hidden{display:none}</style></head>
<body class="flex items-center justify-center min-h-screen p-4 text-gray-800">

<?php if(isset($_GET['msg'])): ?><div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 bg-blue-600"><?= $_GET['msg'] ?></div><?php endif; ?>

<?php if ($A === 'user_center' && isset($_SESSION['user'])): $user=$_SESSION['user']; $udata=getUserData($user['username']); ?>
    <div class="glass-card w-full max-w-md p-8">
        <div class="flex items-center gap-4 mb-6 border-b pb-4"><img src="https://cravatar.eu/helmavatar/<?=$user['realname']?>/64.png" class="w-16 h-16 rounded-xl"><div><h2 class="text-xl font-bold"><?=$user['realname']?></h2><div class="text-xs text-gray-500">签到: <?=$udata['sign_count']??0?> 天</div></div><a href="?action=do_logout" class="ml-auto text-xs bg-red-100 text-red-600 px-3 py-2 rounded">退出</a></div>
        </div>
<?php else: ?>
    <div class="glass-card w-full max-w-sm p-8 text-center relative">
        <h1 class="text-2xl font-bold mb-6 text-blue-600"><?= htmlspecialchars($config['site']['title']) ?></h1>
        
        <div id="box-log">
            <form action="?action=do_login" method="POST" class="space-y-4"><input name="u" placeholder="用户" class="input" required><input type="password" name="p" placeholder="密码" class="input" required><button class="btn-primary">登录</button></form>
            <div class="mt-4 flex justify-between text-sm"><a href="#" onclick="show('box-reg')" class="text-blue-600 font-bold">注册</a><a href="#" onclick="show('box-fp')" class="text-gray-500 hover:text-gray-700">忘记密码?</a></div>
        </div>

        <div id="box-reg" class="hidden">
            <form action="?action=do_reg" method="POST" class="space-y-3"><input name="u" placeholder="用户名" class="input" required><input name="e" placeholder="邮箱" class="input" required><input type="password" name="p" placeholder="密码" class="input" required><div class="flex gap-2"><input name="captcha" placeholder="验证码" class="input" required><img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded border"></div><button class="btn-primary mt-2 bg-green-600">注册</button></form>
            <p class="mt-4 text-sm"><a href="#" onclick="show('box-log')" class="text-blue-600 font-bold">返回登录</a></p>
        </div>

        <div id="box-fp" class="hidden">
            <h3 class="font-bold text-gray-700 mb-4">重置密码</h3>
            <div class="space-y-3 text-left">
                <input id="fp_u" placeholder="用户名" class="input">
                <div class="flex gap-2"><input id="fp_e" placeholder="邮箱" class="input"><button onclick="sendCode()" class="bg-gray-500 text-white px-3 rounded text-xs whitespace-nowrap">获取验证码</button></div>
                <input id="fp_c" placeholder="验证码" class="input">
                <input id="fp_p" type="password" placeholder="新密码" class="input">
                <button onclick="doReset()" class="btn-primary bg-orange-500">确认重置</button>
            </div>
            <p class="mt-4 text-sm"><a href="#" onclick="show('box-log')" class="text-blue-600 font-bold">返回登录</a></p>
        </div>
    </div>
<?php endif; ?>

<script>
function show(id){['box-log','box-reg','box-fp'].forEach(x=>document.getElementById(x).classList.add('hidden'));document.getElementById(id).classList.remove('hidden')}
function sendCode(){let u=document.getElementById('fp_u').value,e=document.getElementById('fp_e').value;if(!u||!e)return alert('缺信息');fetch('?action=do_fp_send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`u=${u}&e=${e}`}).then(r=>r.json()).then(d=>alert(d.m))}
function doReset(){let u=document.getElementById('fp_u').value,c=document.getElementById('fp_c').value,p=document.getElementById('fp_p').value;if(!c||!p)return alert('缺信息');fetch('?action=do_fp_reset',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`u=${u}&c=${c}&p=${p}`}).then(r=>r.json()).then(d=>{alert(d.m);if(d.s)show('box-log')})}
</script>
</body></html>
