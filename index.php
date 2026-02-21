<?php
/**
 * Project: Meteor Nexus (流星枢纽)
 * Version: v2.6.1 (Features Restored + Leviathan Security)
 */

// ☢️ [神盾 0-4] 底层安全环境与 WAF
$is_https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') || $_SERVER['SERVER_PORT'] == 443;
if ($is_https) { session_name('__Secure-MeteorNexus'); } else { session_name('MeteorNexus'); }
session_set_cookie_params(['secure' => $is_https, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_only_cookies', 1); error_reporting(0);

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'])) { header('HTTP/1.1 405 Method Not Allowed'); die('405'); }
header("X-Frame-Options: DENY"); header("X-XSS-Protection: 1; mode=block"); header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https://cravatar.eu https://images.unsplash.com; style-src 'self' 'unsafe-inline'; connect-src 'self' https://api.mcsrvstat.us; frame-src 'none'; object-src 'none';");

$ip = $_SERVER['REMOTE_ADDR'];
$banFile = 'banned_ips.json';
$bannedIps = file_exists($banFile) ? json_decode(file_get_contents($banFile), true) : [];
if (in_array($ip, $bannedIps)) { sleep(3); header('HTTP/1.1 403 Forbidden'); die("<h1 style='color:red;text-align:center;margin-top:20%'>[ 403 FORBIDDEN ] IP BANNED.</h1>"); }

function ban_ip_and_die($reason) {
    global $banFile, $bannedIps, $ip;
    if (!in_array($ip, $bannedIps)) { $bannedIps[] = $ip; file_put_contents($banFile, json_encode($bannedIps), LOCK_EX); }
    sleep(5); header('HTTP/1.1 403 Forbidden'); die("<h1 style='color:red;text-align:center;margin-top:20%'>[ FATAL BREACH ] Rule: {$reason}</h1>");
}

$waf_pattern = '/(union\s+select|insert\s+into|delete\s+from|drop\s+table|truncate\s+|alter\s+table|exec\(|eval\(|base64_decode|<script|javascript:|onerror=|document\.cookie|etc\/passwd|cmd\.exe)/i';
function check_waf($data, $key = '') {
    global $waf_pattern; if ($key === 'html_code') return; 
    if (is_array($data)) { foreach ($data as $k => $v) check_waf($v, $k); } else { if (preg_match($waf_pattern, $data)) ban_ip_and_die("WAF_PAYLOAD_{$key}"); }
}
check_waf($_GET); check_waf($_POST); check_waf($_COOKIE);

$globalRateFile = 'global_ratelimit.json'; $now = microtime(true);
$rateData = file_exists($globalRateFile) ? json_decode(file_get_contents($globalRateFile), true) : [];
if (!isset($rateData[$ip])) $rateData[$ip] = [];
$rateData[$ip] = array_filter($rateData[$ip], function($t) use ($now) { return ($now - $t) < 60; });
$rateData[$ip][] = $now; file_put_contents($globalRateFile, json_encode($rateData), LOCK_EX);
if (count($rateData[$ip]) > 60 || count(array_filter($rateData[$ip], function($t) use ($now) { return ($now - $t) < 1; })) > 5) { header('HTTP/1.1 429 Too Many Requests'); die("<h1 style='color:red;text-align:center;margin-top:20%'>429 RATE LIMITED</h1>"); }

session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($method === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { die(json_encode(['s'=>0,'m'=>'❌ CSRF 拦截'])); }
    $referer = $_SERVER['HTTP_REFERER'] ?? ''; $host = $_SERVER['HTTP_HOST'];
    if (!empty($referer) && parse_url($referer, PHP_URL_HOST) !== $host) ban_ip_and_die("CSRF_REFERER_MISMATCH");
}

if ($method === 'GET') {
    $_SESSION['f_u'] = 'usr_' . bin2hex(random_bytes(4));
    $_SESSION['f_p'] = 'pwd_' . bin2hex(random_bytes(4));
    $_SESSION['f_e'] = 'eml_' . bin2hex(random_bytes(4));
    $_SESSION['f_c'] = 'cpt_' . bin2hex(random_bytes(4));
    $_SESSION['bhv_hash'] = hash('sha256', $_SESSION['csrf_token'] . $ip);
    $_SESSION['form_render_time'] = microtime(true);
}

header('Content-Type: text/html; charset=utf-8');
require_once 'core.php'; 
if (basename($_SERVER['PHP_SELF']) == 'config.php' || defined('IN_ADMIN')) return;

// ☢️ [神盾 6] 会话指纹基因锁
$client_fp = hash('sha256', $ip . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
if (isset($_SESSION['user'])) {
    if (!isset($_SESSION['client_fp'])) $_SESSION['client_fp'] = $client_fp;
    elseif ($_SESSION['client_fp'] !== $client_fp) { session_destroy(); ban_ip_and_die("SESSION_HIJACKING_ATTEMPT"); }
}

$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]); $mode = $config['route']['default'] ?? 'official';
$d_off = strtolower(preg_replace('#^https?://#', '', trim($config['route']['domain_official'] ?? ''))); $d_off = preg_replace('#^www\.#', '', explode('/', $d_off)[0]);
$d_auth = strtolower(preg_replace('#^https?://#', '', trim($config['route']['domain_auth'] ?? ''))); $d_auth = preg_replace('#^www\.#', '', explode('/', $d_auth)[0]);

if (isset($_GET['m'])) { if ($_GET['m'] === 'official') $mode = 'official'; if ($_GET['m'] === 'auth') $mode = 'auth'; } 
else { if (!empty($d_auth) && ($host === $d_auth || $host === "www.$d_auth")) $mode = 'auth'; elseif (!empty($d_off) && ($host === $d_off || $host === "www.$d_off")) $mode = 'official'; }

if ($mode === 'official' && empty($config['modules']['official'])) $mode = 'auth';
if ($mode === 'auth' && empty($config['modules']['auth'])) $mode = 'official';
if (empty($config['modules']['official']) && empty($config['modules']['auth'])) die("<h1 style='text-align:center;margin-top:20vh;'>🚧 维护中</h1>");

if ($mode === 'official') {
    $oType = $config['route']['official_type'] ?? 'local'; $oUrl = $config['route']['official_url'] ?? '';
    if ($oType === 'redirect' && !empty($oUrl)) { header("Location: $oUrl"); exit; }
    if ($oType === 'iframe' && !empty($oUrl)) { die("<!DOCTYPE html><html><head><meta charset='utf-8'><title>".htmlspecialchars($config['site']['title'])."</title><style>body,html{margin:0;padding:0;height:100%;overflow:hidden;}</style></head><body><iframe src='".htmlspecialchars($oUrl)."' width='100%' height='100%' frameborder='0'></iframe>".(!empty($config['modules']['auth']) ? "<a href='?m=auth' style='position:fixed;top:20px;right:20px;background:rgba(255,255,255,0.8);backdrop-filter:blur(5px);padding:8px 16px;border-radius:20px;box-shadow:0 4px 6px rgba(0,0,0,0.1);text-decoration:none;color:#333;font-family:sans-serif;font-size:13px;font-weight:bold;z-index:9999;'>👤 玩家通行证</a>" : "")."</body></html>"); }
    if (file_exists('official.php')) { include 'official.php'; exit; }
    if (file_exists('official.html')) { echo file_get_contents('official.html'); exit; }
    $bg = $config['site']['bg'] ?: 'https://images.unsplash.com/photo-1607988795691-3d0147b43231?q=80&w=1920'; $title = htmlspecialchars($config['site']['title']); $authBtn = !empty($config['modules']['auth']) ? "<a href='?m=auth' class='inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-full shadow-lg'>进入玩家中心 -></a>" : "";
    die("<!DOCTYPE html><html lang='zh-CN'><head><title>{$title} - 官方网站</title><script src='https://cdn.tailwindcss.com'></script></head><body style='background: url(\"{$bg}\") no-repeat center center fixed; background-size: cover;'><div class='min-h-screen bg-black/40 flex flex-col items-center justify-center text-center'><div class='bg-white/10 p-2 rounded-full mb-6 backdrop-blur-md border border-white/20 shadow-2xl'><img src='https://cravatar.eu/helmavatar/Steve/128.png' class='w-24 h-24 rounded-full'></div><h1 class='text-5xl font-extrabold text-white mb-6'>{$title}</h1><p class='text-xl text-gray-200 mb-10'>这里是系统默认展示页。可在后台上传专属官网替换。</p>{$authBtn}</div></body></html>");
}

$A = $_GET['action'] ?? 'home';

// ==========================================
// 业务逻辑处理
// ==========================================
if ($A === 'do_login') { 
    if (isset($_POST['username']) || isset($_POST['password'])) ban_ip_and_die("STATIC_BOT_LOGIN"); // 变异表单防御
    if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS"); // 生物探针验证
    if (!$pdo) { header("Location: ?m=auth&action=login&msg=err_db"); exit; } 
    $u = strtolower(trim($_POST[$_SESSION['f_u']] ?? '')); $p = $_POST[$_SESSION['f_p']] ?? ''; 
    $stmt = $pdo->prepare("SELECT * FROM authme WHERE username=?"); $stmt->execute([$u]); 
    if ($r = $stmt->fetch()) { if (verifyAuthMe($p, $r['password'])) { session_regenerate_id(true); $_SESSION['user'] = $r; $_SESSION['client_fp'] = $client_fp; header("Location: ?m=auth&action=user_center"); } else header("Location: ?m=auth&action=login&msg=err_pass"); } else header("Location: ?m=auth&action=login&msg=err_user"); exit; 
}

if ($A === 'do_logout') { session_destroy(); header("Location: ?m=auth"); exit; }

if ($A === 'do_reg') { 
    if (isset($_POST['username']) || isset($_POST['password']) || isset($_POST['email'])) ban_ip_and_die("STATIC_BOT_REG");
    if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS");
    if (!$pdo) { header("Location: ?m=auth&action=login&msg=err_db"); exit; } 
    if (empty($_SESSION['captcha']) || ($_POST[$_SESSION['f_c']]??'') != $_SESSION['captcha']) { header("Location: ?m=auth&msg=err_captcha"); exit; } 
    
    // 限制时间膨胀、代理穿透与蜜罐
    if (isset($_SESSION['form_render_time']) && (microtime(true) - $_SESSION['form_render_time'] < 2)) ban_ip_and_die("SUPERHUMAN_SPEED");
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) || !empty($_SERVER['HTTP_VIA'])) { header("Location: ?m=auth&msg=err_proxy"); exit; }
    if (isset($_POST['hp_bot_trap']) && !empty($_POST['hp_bot_trap'])) ban_ip_and_die("HONEYPOT");

    $u = trim($_POST[$_SESSION['f_u']] ?? ''); $p = $_POST[$_SESSION['f_p']] ?? ''; $e = trim($_POST[$_SESSION['f_e']] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $u)) { header("Location: ?m=auth&msg=err_format"); exit; } 
    $u = strtolower($u);
    
    if (isset($_COOKIE['mt_device_lock'])) { header("Location: ?m=auth&msg=err_limit"); exit; } 
    $stmtL = $pdo->prepare("SELECT id FROM authme WHERE ip=?"); $stmtL->execute([$ip]); 
    if ($stmtL->fetch()) { header("Location: ?m=auth&msg=err_limit"); exit; } 
    
    $stmt = $pdo->prepare("SELECT id FROM authme WHERE username=?"); $stmt->execute([$u]); 
    if ($stmt->fetch()) { header("Location: ?m=auth&msg=err_exists"); exit; } 
    
    $pdo->prepare("INSERT INTO authme (username,realname,password,email,ip,regdate,lastlogin) VALUES (?,?,?,?,?,?,?)")->execute([$u, $_POST[$_SESSION['f_u']], hashAuthMe($p), $e, $ip, time()*1000, time()*1000]); 
    setcookie('mt_device_lock', hash('sha256', $ip), time() + 315360000, '/'); 
    
    if (!empty($config['rewards']['reg_cmd'])) { runApiCmd(str_replace('%player%', $_POST[$_SESSION['f_u']], $config['rewards']['reg_cmd']), 0); } 
    $smtp = new TinySMTP(); $smtp->send($e, "欢迎加入", "恭喜注册成功！", $config['smtp']); header("Location: ?m=auth&msg=reg_ok"); exit; 
}

// 找回密码、签到、CDK (由于是 AJAX 交互，且已用 CSRF 保护，这里放行固定参数以保证正常运行)
if ($A === 'do_sign' && isset($_SESSION['user'])) { $u = $_SESSION['user']['username']; $d = getUserData($u); $today = date('Ymd'); if (($d['last_sign'] ?? 0) == $today) { echo json_encode(['s'=>0, 'm'=>'📅 今天已签到']); exit; } $targets = $config['rewards']['sign_in_servers'] ?? []; $ok = 0; foreach ($targets as $sid) { if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $config['rewards']['daily_cmd']), $sid)) $ok++; } if ($ok > 0) { setUserData($u, 'last_sign', $today); $count = ($d['sign_count'] ?? 0) + 1; setUserData($u, 'sign_count', $count); echo json_encode(['s'=>1, 'm'=>"✅ 签到成功"]); } else { echo json_encode(['s'=>0, 'm'=>'❌ API 失败']); } exit; }
if ($A === 'do_cdk' && isset($_SESSION['user'])) { $code = trim($_POST['code']); $srvIdx = (int)$_POST['server_id']; $u = $_SESSION['user']['username']; $cdks = getCdks(); if (!isset($cdks[$code])) { echo json_encode(['s'=>0,'m'=>'🚫 无效兑换码']); exit; } $c = $cdks[$code]; if ($c['used'] >= $c['max']) { echo json_encode(['s'=>0,'m'=>'⚠️ 抢光']); exit; } if (in_array($u, $c['users'])) { echo json_encode(['s'=>0,'m'=>'⚠️ 已领']); exit; } if (isset($c['server_id']) && $c['server_id'] !== 'all' && (int)$c['server_id'] !== $srvIdx) { echo json_encode(['s'=>0,'m'=>'❌ 不适用']); exit; } $targetSrv = ($c['server_id'] === 'all') ? $srvIdx : (int)$c['server_id']; if (runApiCmd(str_replace('%player%', $_SESSION['user']['realname'], $c['cmd']), $targetSrv)) { $c['used']++; $c['users'][] = $u; updateCdk($code, $c); echo json_encode(['s'=>1,'m'=>'🎁 成功']); } else { echo json_encode(['s'=>0,'m'=>'❌ 失败']); } exit; }
if ($A === 'do_fp_send') { if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS"); if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开']); exit; } $u = strtolower(trim($_POST['u'])); $e = trim($_POST['e']); $stmt = $pdo->prepare("SELECT id, email FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch(); if (!$r || $r['email'] !== $e) { echo json_encode(['s'=>0, 'm'=>'❌ 匹配失败']); exit; } $code = rand(100000, 999999); $t = time(); try { $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]); } catch (PDOException $e) { if ($e->getCode() == '42S22') { $pdo->exec("ALTER TABLE authme ADD COLUMN reset_code VARCHAR(10), ADD COLUMN reset_time BIGINT"); $pdo->prepare("UPDATE authme SET reset_code=?, reset_time=? WHERE id=?")->execute([$code, $t, $r['id']]); } else { echo json_encode(['s'=>0, 'm'=>'❌ DB异常']); exit; } } $smtp = new TinySMTP(); $smtp->send($e, "重置密码验证码", "您的验证码是: <b>$code</b>", $config['smtp']); echo json_encode(['s'=>1, 'm'=>'✅ 验证码已发送']); exit; }
if ($A === 'do_fp_reset') { if (($_POST['bhv_probe']??'') !== $_SESSION['bhv_hash']) ban_ip_and_die("NO_BIOMETRICS"); if (!$pdo) { echo json_encode(['s'=>0, 'm'=>'❌ 数据库断开']); exit; } $u = strtolower(trim($_POST['u'])); $c = trim($_POST['code']); $p = $_POST['pass']; $stmt = $pdo->prepare("SELECT id, reset_code, reset_time FROM authme WHERE username = ?"); $stmt->execute([$u]); $r = $stmt->fetch(); if (!$r || $r['reset_code'] !== $c) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码错误']); exit; } if (time() - $r['reset_time'] > 600) { echo json_encode(['s'=>0, 'm'=>'❌ 验证码已过期']); exit; } $pdo->prepare("UPDATE authme SET password=?, reset_code=NULL WHERE id=?")->execute([hashAuthMe($p), $r['id']]); echo json_encode(['s'=>1, 'm'=>'🎉 密码修改成功！请登录']); exit; }
if ($A === 'captcha') { $c=rand(1000,9999); $_SESSION['captcha']=$c; $i=imagecreatetruecolor(70,36); imagefill($i,0,0,0x3b82f6); imagestring($i,5,15,10,$c,0xffffff); header("Content-type: image/png"); imagepng($i); exit; }

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
    <script>
        ['mousemove','keydown','touchstart'].forEach(e => 
            window.addEventListener(e, () => { document.querySelectorAll('.bhv_probe').forEach(el => el.value = '<?=$_SESSION['bhv_hash']?>'); }, {once:true})
        );
    </script>
    
    <?php if (!empty($config['modules']['official'])): ?>
        <a href="?m=official" class="fixed top-5 right-5 bg-white/80 backdrop-blur px-4 py-2 rounded-full shadow font-bold text-sm text-gray-700 hover:bg-white transition z-50">🏠 返回官网</a>
    <?php endif; ?>

    <?php if(isset($_GET['msg'])): ?>
    <div class="fixed top-5 left-1/2 -translate-x-1/2 px-6 py-3 rounded-full shadow-lg text-white font-bold z-50 animate-bounce <?= strpos($_GET['msg'],'ok')!==false?'bg-green-500':'bg-red-500' ?>">
        <?= ['reg_ok'=>'🎉 注册成功！', 'err_pass'=>'🔒 密码错误', 'err_exists'=>'⚠️ 账号已存在', 'err_captcha'=>'❌ 验证码错误', 'err_db'=>'❌ 数据库异常', 'err_limit'=>'⛔ 安全拦截：该设备/IP 一生仅限注册一个账号', 'err_format'=>'❌ 游戏名非法 (限3-16位字母数字下划线)', 'err_csrf'=>'❌ 请求伪造或超时，请刷新页面', 'err_proxy'=>'⛔ 代理/VPN拦截'][$_GET['msg']] ?? $_GET['msg'] ?>
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
            <a href="?m=auth&action=do_logout" class="ml-auto text-xs bg-red-50 text-red-500 px-3 py-2 rounded hover:bg-red-100 transition">退出</a>
        </div>
        <button onclick="sign(this)" class="w-full mb-6 py-3 rounded-xl font-bold shadow transition border <?= ($udata['last_sign']??0)==date('Ymd') ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-indigo-50 text-indigo-600 border-indigo-100 hover:bg-indigo-100' ?>">
            <?= ($udata['last_sign']??0)==date('Ymd') ? '✅ 今日已签到' : '📅 每日签到' ?>
        </button>
        <div class="space-y-3">
            <label class="text-xs font-bold text-gray-400 uppercase">CDK 兑换</label>
            <select id="sel_srv" class="input font-bold text-blue-900">
                <?php foreach($config['servers'] as $idx => $srv): ?><option value="<?=$idx?>">🌍 <?= htmlspecialchars($srv['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <input type="hidden" id="csrf" value="<?=$_SESSION['csrf_token']?>">
                <input id="cdk" placeholder="输入兑换码..." class="input">
                <button onclick="cdk()" class="bg-green-600 text-white px-5 rounded-lg font-bold shadow hover:bg-green-700 transition">兑换</button>
            </div>
        </div>
    </div>
    <script>
    function sign(b){ b.disabled=true; b.innerText='...'; fetch('?m=auth&action=do_sign',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`csrf_token=${document.getElementById('csrf').value}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s) { b.innerText='✅ 已签到'; b.className='w-full mb-6 py-3 rounded-xl font-bold shadow transition border bg-gray-100 text-gray-400 cursor-not-allowed'; } else b.disabled=false; }); }
    function cdk(){ let c=document.getElementById('cdk').value; let s=document.getElementById('sel_srv').value; let t=document.getElementById('csrf').value; if(!c)return; fetch('?m=auth&action=do_cdk',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`code=${c}&server_id=${s}&csrf_token=${t}`}).then(r=>r.json()).then(d=>{ alert(d.m); if(d.s)document.getElementById('cdk').value=''; }); }
    </script>
    
    <?php else: ?>
    <div class="glass-card w-full max-w-sm p-8 text-center relative fade-in">
        <h1 class="text-3xl font-extrabold mb-6 bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600 pb-1"><?= htmlspecialchars($config['site']['title']) ?></h1>
        
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
            <h2 class="text-xl font-bold text-gray-700 mb-4">通行证注册</h2>
            <form action="?m=auth&action=do_reg" method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <input type="hidden" name="bhv_probe" class="bhv_probe" value="bot">
                <input type="text" name="hp_bot_trap" style="display:none;position:absolute;left:-9999px;" tabindex="-1" autocomplete="off">
                <input name="<?=$_SESSION['f_u']?>" placeholder="Minecraft 角色名" class="input" required>
                <input name="<?=$_SESSION['f_e']?>" type="email" placeholder="电子邮箱 (用于找回密码)" class="input" required>
                <input type="password" name="<?=$_SESSION['f_p']?>" placeholder="设置密码" class="input" required>
                <div class="flex gap-2">
                    <input name="<?=$_SESSION['f_c']?>" placeholder="验证码" class="input" required>
                    <img src="?action=captcha" onclick="this.src='?action=captcha&'+Math.random()" class="h-11 rounded cursor-pointer border border-gray-200">
                </div>
                <button class="btn-primary mt-2 bg-gradient-to-r from-green-500 to-emerald-600 border-none">确认注册</button>
            </form>
            <p class="mt-6 text-sm"><a href="#" onclick="toggle('box-login')" class="text-blue-600 font-bold hover:underline">已有账号？点击登录</a></p>
        </div>

        <div id="box-login" class="hidden">
            <h2 class="text-xl font-bold text-gray-700 mb-4">通行证登录</h2>
            <form action="?m=auth&action=do_login" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>">
                <input type="hidden" name="bhv_probe" class="bhv_probe" value="bot">
                <input name="<?=$_SESSION['f_u']?>" placeholder="游戏角色名" class="input" required>
                <input type="password" name="<?=$_SESSION['f_p']?>" placeholder="密码" class="input" required>
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
                <input type="hidden" id="csrf" value="<?=$_SESSION['csrf_token']?>">
                <input type="hidden" id="bhv_probe_fp" class="bhv_probe" value="bot">
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
    
    // 🔥 恢复：找回密码 JS 逻辑 (加入了 CSRF 和生物探针传输)
    function sendCode() { 
        let u=document.getElementById('fp_u').value, e=document.getElementById('fp_e').value, t=document.getElementById('csrf').value, b=document.getElementById('bhv_probe_fp').value; 
        if(!u || !e) { alert('请填写用户名和邮箱'); return; } 
        fetch('?m=auth&action=do_fp_send', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&e=${e}&csrf_token=${t}&bhv_probe=${b}` })
        .then(r=>r.json()).then(d => alert(d.m)); 
    }
    
    function doReset() { 
        let u=document.getElementById('fp_u').value, c=document.getElementById('fp_c').value, p=document.getElementById('fp_p').value, t=document.getElementById('csrf').value, b=document.getElementById('bhv_probe_fp').value; 
        if(!c || !p) { alert('请填写完整信息'); return; } 
        fetch('?m=auth&action=do_fp_reset', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`u=${u}&code=${c}&pass=${p}&csrf_token=${t}&bhv_probe=${b}` })
        .then(r=>r.json()).then(d => { alert(d.m); if(d.s) toggle('box-login'); }); 
    }
    </script>
    <?php endif; ?>
</body>
</html>
